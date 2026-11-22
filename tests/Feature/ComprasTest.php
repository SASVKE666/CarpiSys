<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $prov;
    private Item $tablero;
    private Item $bisagra;
    private Location $bodega;
    private Location $taller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prov    = Supplier::create(['name' => 'Maderas del Norte', 'is_active' => true]);
        $this->bodega  = Location::create(['name' => 'Bodega', 'is_active' => true]);
        $this->taller  = Location::create(['name' => 'Taller', 'is_active' => true]);
        $this->tablero = Item::create(['name' => 'Tablero', 'sku' => 'T-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 100]);
        $this->bisagra = Item::create(['name' => 'Bisagra', 'sku' => 'B-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 10]);
        // Ya hay 10 tableros a $100 en la bodega
        InventoryStock::create(['item_id' => $this->tablero->id, 'location_id' => $this->bodega->id, 'quantity' => 10, 'avg_cost' => 100]);
    }

    private function admin(): User    { return User::factory()->admin()->create(); }
    private function operario(): User { return User::factory()->operario()->create(); }

    /** Crea una orden como administrador: 10 tableros a $200 y 50 bisagras a $8. */
    private function crear(array $over = []): PurchaseOrder
    {
        $this->actingAs($this->admin())->post('/purchase-orders', array_merge([
            'supplier_id' => $this->prov->id,
            'tax_rate'    => 16,
            'items'       => [
                ['item_id' => $this->tablero->id, 'quantity' => 10, 'unit_cost' => 200],
                ['item_id' => $this->bisagra->id, 'quantity' => 50, 'unit_cost' => 8],
            ],
        ], $over))->assertSessionHasNoErrors();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    private function enviada(): PurchaseOrder
    {
        $po = $this->crear();
        $this->actingAs($this->admin())->post("/purchase-orders/{$po->id}/send")->assertSessionHasNoErrors();
        return $po->fresh();
    }

    /** @param array<int,float> $qtys cantidades por orden de renglón (tablero, bisagra) */
    private function recibir(User $u, PurchaseOrder $po, array $qtys, ?Location $loc = null, array $costs = [])
    {
        $lines = [];
        foreach ($po->items()->orderBy('id')->get() as $i => $l) {
            $row = ['line_id' => $l->id, 'quantity' => $qtys[$i] ?? 0, 'location_id' => ($loc ?? $this->bodega)->id];
            if (isset($costs[$i])) $row['unit_cost'] = $costs[$i];
            $lines[] = $row;
        }
        return $this->actingAs($u)->post("/purchase-orders/{$po->id}/receive", ['lines' => $lines]);
    }

    private function stock(Item $i, ?Location $loc = null): float
    {
        return (float) InventoryStock::where('item_id', $i->id)->where('location_id', ($loc ?? $this->bodega)->id)->value('quantity');
    }

    // ───────────────────────────── Crear y enviar ─────────────────────────────

    public function test_admin_crea_orden_en_borrador_con_totales_e_iva(): void
    {
        $po = $this->crear();

        $this->assertSame('OC-0001', $po->code);
        $this->assertSame('borrador', $po->status);
        $this->assertSame(2, $po->items()->count());
        // 10×200 + 50×8 = 2,400 ; IVA 16% = 384 ; total 2,784
        $this->assertEquals(2400, $po->subtotal);
        $this->assertEquals(384, $po->tax);
        $this->assertEquals(2784, $po->total);
        $this->assertNull($po->ordered_date);
    }

    public function test_crear_una_orden_no_mueve_inventario(): void
    {
        $this->crear();

        $this->assertSame(10.0, $this->stock($this->tablero));
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_validaciones_de_la_orden(): void
    {
        $admin = $this->admin();

        // sin artículos
        $this->actingAs($admin)->post('/purchase-orders', ['supplier_id' => $this->prov->id, 'items' => []])
            ->assertSessionHasErrors('items');

        // artículo repetido
        $this->actingAs($admin)->post('/purchase-orders', [
            'supplier_id' => $this->prov->id,
            'items' => [
                ['item_id' => $this->tablero->id, 'quantity' => 1, 'unit_cost' => 5],
                ['item_id' => $this->tablero->id, 'quantity' => 2, 'unit_cost' => 5],
            ],
        ])->assertSessionHasErrors('items.0.item_id');

        // cantidad cero
        $this->actingAs($admin)->post('/purchase-orders', [
            'supplier_id' => $this->prov->id,
            'items' => [['item_id' => $this->tablero->id, 'quantity' => 0, 'unit_cost' => 5]],
        ])->assertSessionHasErrors('items.0.quantity');

        // proveedor desactivado
        $this->prov->update(['is_active' => false]);
        $this->actingAs($admin)->post('/purchase-orders', [
            'supplier_id' => $this->prov->id,
            'items' => [['item_id' => $this->tablero->id, 'quantity' => 1, 'unit_cost' => 5]],
        ])->assertSessionHasErrors('supplier_id');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_enviar_cambia_estado_y_fija_fecha(): void
    {
        $po = $this->enviada();

        $this->assertSame('enviada', $po->status);
        $this->assertSame(now()->toDateString(), $po->ordered_date->toDateString());
    }

    public function test_solo_el_borrador_se_edita_o_se_borra(): void
    {
        $admin = $this->admin();
        $po = $this->crear();

        $this->actingAs($admin)->put("/purchase-orders/{$po->id}", [
            'supplier_id' => $this->prov->id, 'tax_rate' => 0,
            'items' => [['item_id' => $this->bisagra->id, 'quantity' => 4, 'unit_cost' => 10]],
        ])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame(1, $po->items()->count());
        $this->assertEquals(40, $po->total);

        $this->actingAs($admin)->post("/purchase-orders/{$po->id}/send");
        $this->actingAs($admin)->put("/purchase-orders/{$po->id}", [
            'supplier_id' => $this->prov->id,
            'items' => [['item_id' => $this->bisagra->id, 'quantity' => 99, 'unit_cost' => 10]],
        ])->assertSessionHasErrors('order');
        $this->actingAs($admin)->delete("/purchase-orders/{$po->id}")->assertSessionHasErrors('order');

        $this->assertEquals(4, $po->items()->first()->quantity_ordered);
        $this->assertNotNull(PurchaseOrder::find($po->id));
    }

    public function test_borrador_se_puede_borrar(): void
    {
        $po = $this->crear();

        $this->actingAs($this->admin())->delete("/purchase-orders/{$po->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, \App\Models\PurchaseOrderItem::count());
    }

    // ───────────────────────────── Permisos ─────────────────────────────

    public function test_operario_no_crea_edita_envia_cancela_ni_borra(): void
    {
        $po = $this->crear();
        $op = $this->operario();

        $this->actingAs($op)->post('/purchase-orders', [
            'supplier_id' => $this->prov->id,
            'items' => [['item_id' => $this->tablero->id, 'quantity' => 1, 'unit_cost' => 5]],
        ])->assertForbidden();
        $this->actingAs($op)->put("/purchase-orders/{$po->id}", [])->assertForbidden();
        $this->actingAs($op)->post("/purchase-orders/{$po->id}/send")->assertForbidden();
        $this->actingAs($op)->post("/purchase-orders/{$po->id}/cancel")->assertForbidden();
        $this->actingAs($op)->post("/purchase-orders/{$po->id}/close")->assertForbidden();
        $this->actingAs($op)->delete("/purchase-orders/{$po->id}")->assertForbidden();
        $this->actingAs($op)->post('/suppliers', ['name' => 'X'])->assertForbidden();

        $this->assertSame('borrador', $po->fresh()->status);
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_admin_administra_proveedores(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/suppliers', ['name' => 'Herrajes Tepic', 'phone' => '311 000 0000'])
            ->assertSessionHasNoErrors();
        $s = Supplier::where('name', 'Herrajes Tepic')->firstOrFail();

        $this->actingAs($admin)->put("/suppliers/{$s->id}", ['name' => 'Herrajes Tepic', 'is_active' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($s->fresh()->is_active);

        $this->actingAs($admin)->post('/suppliers', ['name' => ''])->assertSessionHasErrors('name');
    }

    // ───────────────────────────── Recepción ─────────────────────────────

    public function test_recibir_todo_genera_entradas_actualiza_stock_y_costo_promedio(): void
    {
        $po = $this->enviada();

        $this->recibir($this->operario(), $po, [10, 50])->assertSessionHasNoErrors();

        // tableros: 10 + 10 ; bisagras: 0 + 50
        $this->assertSame(20.0, $this->stock($this->tablero));
        $this->assertSame(50.0, $this->stock($this->bisagra));

        $po->refresh();
        $this->assertSame('recibida', $po->status);
        $this->assertSame(now()->toDateString(), $po->received_date->toDateString());

        $movs = InventoryMovement::orderBy('id')->get();
        $this->assertCount(2, $movs);
        $this->assertSame('entrada', $movs[0]->movement_type);
        $this->assertSame('purchase_order', $movs[0]->reference_type);
        $this->assertSame($po->id, (int) $movs[0]->reference_id);
        $this->assertEquals(200, $movs[0]->unit_cost);

        // costo promedio ponderado del tablero: (10×100 + 10×200) / 20 = 150
        $this->assertEquals(150, $this->tablero->fresh()->unit_cost);
        // la bisagra no tenía existencia: queda al costo de la compra
        $this->assertEquals(8, $this->bisagra->fresh()->unit_cost);
    }

    public function test_recepcion_parcial_y_luego_el_resto(): void
    {
        $po = $this->enviada();
        $op = $this->operario();

        $this->recibir($op, $po, [4, 0])->assertSessionHasNoErrors();
        $this->assertSame('parcial', $po->fresh()->status);
        $this->assertNull($po->fresh()->received_date);
        $this->assertSame(14.0, $this->stock($this->tablero));
        $this->assertSame(0.0, $this->stock($this->bisagra));

        $this->recibir($op, $po, [6, 50])->assertSessionHasNoErrors();
        $this->assertSame('recibida', $po->fresh()->status);
        $this->assertSame(20.0, $this->stock($this->tablero));
        $this->assertSame(50.0, $this->stock($this->bisagra));
        $this->assertSame(3, InventoryMovement::count());
    }

    public function test_no_se_puede_recibir_mas_de_lo_pendiente_y_no_queda_nada_a_medias(): void
    {
        $po = $this->enviada();

        // el primer renglón es válido, el segundo se pasa: no debe registrarse ninguno
        $this->recibir($this->operario(), $po, [5, 51])->assertSessionHasErrors('lines');

        $this->assertSame(10.0, $this->stock($this->tablero));
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame('enviada', $po->fresh()->status);
        $this->assertEquals(0, $po->items()->sum('quantity_received'));
        $this->assertEquals(100, $this->tablero->fresh()->unit_cost);
    }

    public function test_recibir_sin_cantidades_da_error(): void
    {
        $po = $this->enviada();

        $this->recibir($this->operario(), $po, [0, 0])->assertSessionHasErrors('lines');
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_no_se_recibe_un_borrador_ni_una_cancelada(): void
    {
        $po = $this->crear();
        $this->recibir($this->operario(), $po, [1, 0])->assertSessionHasErrors('order');

        $this->actingAs($this->admin())->post("/purchase-orders/{$po->id}/cancel");
        $this->assertSame('cancelada', $po->fresh()->status);
        $this->recibir($this->operario(), $po, [1, 0])->assertSessionHasErrors('order');

        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_la_recepcion_puede_ir_a_otra_ubicacion(): void
    {
        $po = $this->enviada();

        $this->recibir($this->operario(), $po, [3, 0], $this->taller)->assertSessionHasNoErrors();

        $this->assertSame(3.0, $this->stock($this->tablero, $this->taller));
        $this->assertSame(10.0, $this->stock($this->tablero, $this->bodega));
    }

    public function test_el_operario_no_puede_cambiar_el_costo_pero_el_admin_si(): void
    {
        $po = $this->enviada();

        // el operario manda un costo distinto: se ignora, se usa el de la orden ($200)
        $this->recibir($this->operario(), $po, [2, 0], null, [1])->assertSessionHasNoErrors();
        $this->assertEquals(200, $po->items()->orderBy('id')->first()->unit_cost);
        $this->assertEquals(200, InventoryMovement::first()->unit_cost);

        // el admin corrige el costo al recibir (la factura llegó a $220): se recalcula el total de la orden
        $this->recibir($this->admin(), $po, [8, 0], null, [220])->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertEquals(220, $po->items()->orderBy('id')->first()->unit_cost);
        // 10×220 + 50×8 = 2,600 ; IVA 416 ; total 3,016
        $this->assertEquals(2600, $po->subtotal);
        $this->assertEquals(3016, $po->total);
    }

    // ───────────────────────────── Cerrar y cancelar ─────────────────────────────

    public function test_dar_por_recibida_ajusta_la_orden_a_lo_que_llego(): void
    {
        $po = $this->enviada();
        $this->recibir($this->operario(), $po, [6, 0]);
        $this->assertSame('parcial', $po->fresh()->status);

        $this->actingAs($this->admin())->post("/purchase-orders/{$po->id}/close")->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame('recibida', $po->status);
        $this->assertSame(1, $po->items()->count());          // la bisagra no llegó: se quita
        $this->assertEquals(6, $po->items()->first()->quantity_ordered);
        $this->assertEquals(1200, $po->subtotal);              // 6 × 200
        $this->assertEquals(1392, $po->total);                 // + 16%
        $this->assertSame(16.0, $this->stock($this->tablero));
    }

    public function test_cancelar_borrador_o_enviada_si_pero_parcial_no(): void
    {
        $admin = $this->admin();

        $a = $this->crear();
        $this->actingAs($admin)->post("/purchase-orders/{$a->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelada', $a->fresh()->status);

        $b = $this->enviada();
        $this->actingAs($admin)->post("/purchase-orders/{$b->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelada', $b->fresh()->status);

        $c = $this->enviada();
        $this->recibir($this->operario(), $c, [1, 0]);
        $this->actingAs($admin)->post("/purchase-orders/{$c->id}/cancel")->assertSessionHasErrors('order');
        $this->assertSame('parcial', $c->fresh()->status);
    }

    public function test_dar_por_recibida_solo_aplica_a_parciales(): void
    {
        $po = $this->enviada();

        $this->actingAs($this->admin())->post("/purchase-orders/{$po->id}/close")->assertSessionHasErrors('order');
        $this->assertSame('enviada', $po->fresh()->status);
    }

    // ───────────────────────────── Datos para la pantalla ─────────────────────────────

    public function test_el_operario_ve_las_compras_sin_importes(): void
    {
        $this->enviada();

        $this->actingAs($this->operario())->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchasesData', 1)
                ->where('purchasesData.0.code', 'OC-0001')
                ->where('purchasesData.0.status', 'enviada')
                ->where('purchasesData.0.supplier', 'Maderas del Norte')
                ->where('purchasesData.0.total', null)
                ->where('purchasesData.0.subtotal', null)
                ->where('purchasesData.0.tax', null)
                ->where('purchasesData.0.tax_rate', null)
                ->where('purchasesData.0.lines.0.cost', null)
                ->where('purchasesData.0.lines.0.subtotal', null)
                ->where('purchasesData.0.lines.0.ordered', 10)
                ->where('purchasesData.0.lines.0.received', 0));
    }

    public function test_el_admin_ve_los_importes_y_el_movimiento_lleva_el_codigo_de_la_compra(): void
    {
        $po = $this->enviada();
        $this->recibir($this->admin(), $po, [10, 50]);

        $this->actingAs($this->admin())->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchasesData.0.total', 2784)
                ->where('purchasesData.0.status', 'recibida')
                ->where('purchasesData.0.lines.0.cost', 200)
                ->where('movementsData.0.order', 'OC-0001'));
    }
}

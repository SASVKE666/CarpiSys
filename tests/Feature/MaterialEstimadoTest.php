<?php

namespace Tests\Feature;

use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEstimate;
use App\Services\MaterialVarianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaterialEstimadoTest extends TestCase
{
    use RefreshDatabase;

    private Item $tablero;
    private Item $bisagra;
    private Item $sierra;
    private Location $bodega;
    private WorkOrder $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodega  = Location::create(['name' => 'Bodega', 'is_active' => true]);
        $this->tablero = Item::create(['name' => 'Tablero', 'sku' => 'T-1', 'item_type' => 'materia_prima', 'unit' => 'hoja', 'unit_cost' => 650]);
        $this->bisagra = Item::create(['name' => 'Bisagra', 'sku' => 'B-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 25]);
        $this->sierra  = Item::create(['name' => 'Sierra', 'sku' => 'S-1', 'item_type' => 'herramienta', 'unit' => 'pieza', 'unit_cost' => 900]);
        foreach ([$this->tablero, $this->bisagra] as $i) {
            InventoryStock::create(['item_id' => $i->id, 'location_id' => $this->bodega->id, 'quantity' => 100, 'avg_cost' => $i->unit_cost]);
        }

        $this->orden = WorkOrder::create(['client_name' => 'Cliente', 'description' => 'Cocina', 'quoted_price' => 20000, 'status' => 'en_proceso']);
    }

    private function admin(): User    { return User::factory()->admin()->create(); }
    private function operario(): User { return User::factory()->operario()->create(); }

    private function estimar(User $u, Item $item, float $qty, ?WorkOrder $o = null)
    {
        return $this->actingAs($u)->post('/work-orders/' . ($o ?? $this->orden)->id . '/estimates', [
            'item_id' => $item->id, 'quantity' => $qty,
        ]);
    }

    private function usar(Item $item, float $qty)
    {
        return $this->actingAs($this->admin())->post('/work-orders/' . $this->orden->id . '/materials', [
            'item_id' => $item->id, 'location_id' => $this->bodega->id, 'quantity' => $qty,
        ]);
    }

    private function lineas(): array
    {
        $o = WorkOrder::with(['materials.item', 'estimates.item'])->find($this->orden->id);
        return collect(app(MaterialVarianceService::class)->lines($o))->keyBy('name')->all();
    }

    public function test_el_admin_agrega_y_cambia_el_material_estimado_sin_mover_inventario(): void
    {
        $this->estimar($this->admin(), $this->tablero, 4)->assertSessionHasNoErrors();
        $this->estimar($this->admin(), $this->tablero, 5);   // misma línea: cambia la cantidad

        $this->assertSame(1, WorkOrderEstimate::count());
        $this->assertEquals(5, WorkOrderEstimate::first()->quantity);
        $this->assertEquals(100, InventoryStock::where('item_id', $this->tablero->id)->value('quantity'), 'estimar no descuenta inventario');
        $this->assertEquals(0, $this->orden->fresh()->materials_cost, 'ni cambia costos');
    }

    public function test_un_operario_no_puede_estimar_ni_quitar(): void
    {
        $this->estimar($this->operario(), $this->tablero, 4)->assertForbidden();

        $e = WorkOrderEstimate::create(['work_order_id' => $this->orden->id, 'item_id' => $this->tablero->id, 'quantity' => 4]);
        $this->actingAs($this->operario())
            ->delete("/work-orders/{$this->orden->id}/estimates/{$e->id}")
            ->assertForbidden();
        $this->assertSame(1, WorkOrderEstimate::count());
    }

    public function test_el_admin_quita_una_linea_y_no_borra_las_de_otra_orden(): void
    {
        $otra = WorkOrder::create(['client_name' => 'Otro', 'description' => 'Closet', 'status' => 'pendiente']);
        $e    = WorkOrderEstimate::create(['work_order_id' => $this->orden->id, 'item_id' => $this->tablero->id, 'quantity' => 4]);

        $this->actingAs($this->admin())->delete("/work-orders/{$otra->id}/estimates/{$e->id}")->assertNotFound();
        $this->assertSame(1, WorkOrderEstimate::count());

        $this->actingAs($this->admin())->delete("/work-orders/{$this->orden->id}/estimates/{$e->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, WorkOrderEstimate::count());
    }

    public function test_no_se_estiman_herramientas_ni_cantidades_invalidas(): void
    {
        $this->estimar($this->admin(), $this->sierra, 1)->assertSessionHasErrors('item_id');
        $this->estimar($this->admin(), $this->tablero, 0)->assertSessionHasErrors('quantity');
        $this->assertSame(0, WorkOrderEstimate::count());
    }

    public function test_una_orden_cerrada_no_admite_cambios_al_estimado(): void
    {
        $this->orden->update(['status' => 'completada']);

        $this->estimar($this->admin(), $this->tablero, 4)->assertSessionHasErrors('quantity');
        $this->assertSame(0, WorkOrderEstimate::count());
    }

    public function test_compara_lo_estimado_con_lo_registrado(): void
    {
        $this->estimar($this->admin(), $this->tablero, 10);
        $this->estimar($this->admin(), $this->bisagra, 10);
        $this->usar($this->tablero, 9.5);    // 95 %: dentro de lo esperado
        $this->usar($this->bisagra, 4);      // 40 %: falta registro o se usó menos

        $l = $this->lineas();
        $this->assertSame('ok', $l['Tablero']['status']);
        $this->assertEquals(-0.5, $l['Tablero']['diff']);
        $this->assertEquals(-5.0, $l['Tablero']['pct']);
        $this->assertSame('bajo', $l['Bisagra']['status']);
        $this->assertEquals(-60.0, $l['Bisagra']['pct']);
    }

    public function test_marca_el_exceso_y_el_material_sin_estimado(): void
    {
        $this->estimar($this->admin(), $this->tablero, 10);
        $this->usar($this->tablero, 12);     // 120 %: exceso (merma o uso mayor)
        $this->usar($this->bisagra, 3);      // no estaba en el plan

        $l = $this->lineas();
        $this->assertSame('exceso', $l['Tablero']['status']);
        $this->assertEquals(20.0, $l['Tablero']['pct']);
        $this->assertSame('sin_estimado', $l['Bisagra']['status']);
        $this->assertNull($l['Bisagra']['pct']);
    }

    public function test_los_limites_del_90_y_el_110_cuentan_como_dentro(): void
    {
        $s = app(MaterialVarianceService::class);

        $this->assertSame('ok', $s->status(10, 9.0));
        $this->assertSame('ok', $s->status(10, 11.0));
        $this->assertSame('bajo', $s->status(10, 8.99));
        $this->assertSame('exceso', $s->status(10, 11.01));
        $this->assertSame('sin_estimado', $s->status(0, 5));
    }

    public function test_el_resumen_solo_cuenta_ordenes_completadas_con_estimado(): void
    {
        $this->estimar($this->admin(), $this->tablero, 10);
        $this->estimar($this->admin(), $this->bisagra, 10);
        $this->usar($this->tablero, 10);
        $this->usar($this->bisagra, 5);

        $abierta = WorkOrder::with(['materials.item', 'estimates.item'])->get();
        $this->assertSame(0, app(MaterialVarianceService::class)->summary($abierta)['total']['orders'], 'abierta no entra');

        $this->orden->update(['status' => 'completada']);
        // otra orden completada pero sin estimado: tampoco entra
        WorkOrder::create(['client_name' => 'X', 'description' => 'Y', 'status' => 'completada']);

        $r = app(MaterialVarianceService::class)->summary(WorkOrder::with(['materials.item', 'estimates.item'])->get());

        $this->assertSame(1, $r['total']['orders']);
        $this->assertSame(2, $r['total']['lines']);
        $this->assertSame(1, $r['total']['ok']);
        $this->assertSame(1, $r['total']['low']);
        $this->assertEquals(50.0, $r['total']['coverage']);
        $this->assertEquals(50.0, $r['orders'][0]['coverage']);
    }

    public function test_el_resumen_llega_solo_al_admin_y_sin_importes(): void
    {
        $this->estimar($this->admin(), $this->tablero, 10);

        $this->actingAs($this->operario())->get('/')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('varianceSummary', null)
                ->where('ordersData.0.estimates.0.estimated', 10)   // el plan sí lo ve (son cantidades)
                ->missing('ordersData.0.estimates.0.cost'));

        $this->actingAs($this->admin())->get('/')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('varianceSummary.total')
                ->where('varianceSummary.total.orders', 0));
    }
}

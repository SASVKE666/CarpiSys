<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\LaborRecord;
use App\Models\Location;
use App\Models\OverheadRate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CosteoTest extends TestCase
{
    use RefreshDatabase;

    private Item $tablero;
    private Item $bisagra;
    private Location $bodega;
    private WorkOrder $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bodega  = Location::create(['name' => 'Bodega', 'is_active' => true]);
        $this->tablero = Item::create(['name' => 'Tablero', 'sku' => 'T-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 650]);
        $this->bisagra = Item::create(['name' => 'Bisagra', 'sku' => 'B-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 25.50]);
        InventoryStock::create(['item_id' => $this->tablero->id, 'location_id' => $this->bodega->id, 'quantity' => 15, 'avg_cost' => 650]);
        InventoryStock::create(['item_id' => $this->bisagra->id, 'location_id' => $this->bodega->id, 'quantity' => 100, 'avg_cost' => 25.5]);

        $this->orden = WorkOrder::create(['client_name' => 'Cliente', 'description' => 'Cocina', 'quoted_price' => 20000]);
    }

    private function admin(array $attrs = []): User    { return User::factory()->admin()->create($attrs); }
    private function operario(array $attrs = []): User { return User::factory()->operario()->create($attrs); }

    private function usar(User $u, Item $item, float $qty, ?WorkOrder $orden = null)
    {
        return $this->actingAs($u)->post('/work-orders/' . ($orden ?? $this->orden)->id . '/materials', [
            'item_id' => $item->id, 'location_id' => $this->bodega->id, 'quantity' => $qty,
        ]);
    }

    private function stock(Item $item): float
    {
        return (float) InventoryStock::where('item_id', $item->id)->where('location_id', $this->bodega->id)->value('quantity');
    }

    // ───────────────────────────── Materiales ─────────────────────────────

    public function test_usar_material_descuenta_stock_registra_movimiento_y_costea(): void
    {
        $op = $this->operario();

        $this->usar($op, $this->tablero, 2)->assertSessionHasNoErrors();

        $this->assertSame(13.0, $this->stock($this->tablero));

        $mov = InventoryMovement::firstOrFail();
        $this->assertSame('salida', $mov->movement_type);
        $this->assertSame('work_order', $mov->reference_type);
        $this->assertSame($this->orden->id, (int) $mov->reference_id);
        $this->assertSame($op->id, $mov->user_id);
        $this->assertNotNull($mov->created_at);

        $mat = WorkOrderMaterial::firstOrFail();
        $this->assertSame($mov->id, $mat->movement_id);
        $this->assertEquals(650, $mat->unit_cost);
        $this->assertEquals(1300, $this->orden->fresh()->materials_cost);
    }

    public function test_stock_insuficiente_se_rechaza_sin_efectos_secundarios(): void
    {
        $this->usar($this->operario(), $this->tablero, 16)->assertSessionHasErrors('quantity');

        $this->assertSame(15.0, $this->stock($this->tablero));
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, WorkOrderMaterial::count());
        $this->assertEquals(0, $this->orden->fresh()->materials_cost);
    }

    public function test_el_costo_se_congela_al_usar_el_material(): void
    {
        $op = $this->operario();
        $this->usar($op, $this->tablero, 1);

        $this->tablero->update(['unit_cost' => 800]);   // sube el precio después
        $this->usar($op, $this->tablero, 1);

        $this->assertEquals(650 + 800, $this->orden->fresh()->materials_cost);
        $this->assertEquals([650.0, 800.0], WorkOrderMaterial::orderBy('id')->pluck('unit_cost')->map(fn ($c) => (float) $c)->all());
    }

    public function test_quitar_material_lo_devuelve_al_inventario_y_baja_el_costo(): void
    {
        $op = $this->operario();
        $this->usar($op, $this->tablero, 3);
        $mat = WorkOrderMaterial::firstOrFail();

        $this->actingAs($op)->delete("/work-orders/{$this->orden->id}/materials/{$mat->id}")->assertSessionHasNoErrors();

        $this->assertSame(15.0, $this->stock($this->tablero));
        $this->assertSame(0, WorkOrderMaterial::count());
        $this->assertEquals(0, $this->orden->fresh()->materials_cost);
        $this->assertSame(['salida', 'devolucion'], InventoryMovement::orderBy('id')->pluck('movement_type')->all());
    }

    public function test_un_operario_no_puede_quitar_material_de_otro_pero_el_admin_si(): void
    {
        $this->usar($this->operario(), $this->tablero, 1);
        $mat = WorkOrderMaterial::firstOrFail();
        $url = "/work-orders/{$this->orden->id}/materials/{$mat->id}";

        $this->actingAs($this->operario())->delete($url)->assertForbidden();
        $this->assertSame(1, WorkOrderMaterial::count());

        $this->actingAs($this->admin())->delete($url)->assertSessionHasNoErrors();
        $this->assertSame(0, WorkOrderMaterial::count());
    }

    public function test_orden_cerrada_no_admite_materiales_ni_horas(): void
    {
        $this->orden->update(['status' => 'completada']);
        $op = $this->operario(['hourly_rate' => 50]);

        $this->usar($op, $this->tablero, 1)->assertSessionHasErrors('order');
        $this->actingAs($op)->post("/work-orders/{$this->orden->id}/labor", ['hours' => 2])->assertSessionHasErrors('order');

        $this->assertSame(15.0, $this->stock($this->tablero));
        $this->assertSame(0, WorkOrderMaterial::count());
        $this->assertSame(0, LaborRecord::count());
    }

    // ───────────────────────────── Mano de obra ─────────────────────────────

    public function test_el_operario_registra_horas_con_su_propia_tarifa(): void
    {
        $op = $this->operario(['name' => 'Juan', 'hourly_rate' => 60]);

        $this->actingAs($op)->post("/work-orders/{$this->orden->id}/labor", [
            'hours' => 2.5, 'task_description' => 'Corte',
            // intento de falsear nombre y tarifa: se ignora
            'worker_name' => 'Otro', 'hourly_rate' => 1,
        ])->assertSessionHasNoErrors();

        $rec = LaborRecord::firstOrFail();
        $this->assertSame('Juan', $rec->worker_name);
        $this->assertEquals(60, $rec->hourly_rate);
        $this->assertSame($op->id, $rec->user_id);
        $this->assertNotNull($rec->created_at);
        $this->assertEquals(150, $this->orden->fresh()->labor_cost);
    }

    public function test_operario_sin_tarifa_no_puede_registrar_horas(): void
    {
        $this->actingAs($this->operario())->post("/work-orders/{$this->orden->id}/labor", ['hours' => 2])
            ->assertSessionHasErrors('hours');

        $this->assertSame(0, LaborRecord::count());
    }

    public function test_el_admin_registra_horas_de_personal_sin_cuenta(): void
    {
        $this->actingAs($this->admin())->post("/work-orders/{$this->orden->id}/labor", [
            'hours' => 4, 'worker_name' => 'Pedro (ayudante)', 'hourly_rate' => 45,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(180, $this->orden->fresh()->labor_cost);
        $this->assertSame('Pedro (ayudante)', LaborRecord::firstOrFail()->worker_name);
    }

    public function test_validacion_de_horas_y_fecha(): void
    {
        $op = $this->operario(['hourly_rate' => 50]);
        $url = "/work-orders/{$this->orden->id}/labor";

        $this->actingAs($op)->post($url, ['hours' => 0])->assertSessionHasErrors('hours');
        $this->actingAs($op)->post($url, ['hours' => 30])->assertSessionHasErrors('hours');
        $this->actingAs($op)->post($url, ['hours' => 2, 'work_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('work_date');
        $this->assertSame(0, LaborRecord::count());
    }

    public function test_solo_el_admin_borra_horas(): void
    {
        $op = $this->operario(['hourly_rate' => 50]);
        $this->actingAs($op)->post("/work-orders/{$this->orden->id}/labor", ['hours' => 2]);
        $rec = LaborRecord::firstOrFail();
        $url = "/work-orders/{$this->orden->id}/labor/{$rec->id}";

        $this->actingAs($op)->delete($url)->assertForbidden();
        $this->assertEquals(100, $this->orden->fresh()->labor_cost);

        $this->actingAs($this->admin())->delete($url)->assertSessionHasNoErrors();
        $this->assertSame(0, LaborRecord::count());
        $this->assertEquals(0, $this->orden->fresh()->labor_cost);
    }

    // ───────────────────────────── CIF ─────────────────────────────

    public function test_el_cif_se_calcula_con_las_tasas_activas(): void
    {
        OverheadRate::create(['name' => 'Luz y renta', 'rate_type' => 'porcentaje_mano_obra',  'value' => 50, 'is_active' => true]);
        OverheadRate::create(['name' => 'Consumibles', 'rate_type' => 'porcentaje_materiales', 'value' => 10, 'is_active' => true]);
        OverheadRate::create(['name' => 'Escuadradora', 'rate_type' => 'por_hora_maquina',     'value' => 20, 'is_active' => true]);
        OverheadRate::create(['name' => 'Apagada',      'rate_type' => 'porcentaje_mano_obra',  'value' => 99, 'is_active' => false]);
        OverheadRate::create(['name' => 'Fija',         'rate_type' => 'monto_fijo_mensual',    'value' => 5000, 'is_active' => true]);
        $this->orden->update(['machine_hours' => 3]);

        $op = $this->operario(['hourly_rate' => 100]);
        $this->usar($op, $this->tablero, 2);                                                     // MD = 1300
        $this->actingAs($op)->post("/work-orders/{$this->orden->id}/labor", ['hours' => 4]);     // MOD = 400

        $o = $this->orden->fresh();
        $this->assertEquals(1300, $o->materials_cost);
        $this->assertEquals(400, $o->labor_cost);
        // 50% de 400 = 200  +  10% de 1300 = 130  +  3 h × 20 = 60  → 390 (la inactiva y la fija no cuentan)
        $this->assertEquals(390, $o->overhead_cost);
        $this->assertEquals(1300 + 400 + 390, $o->total_cost);
    }

    public function test_sin_tasas_el_cif_es_cero(): void
    {
        $this->usar($this->operario(), $this->tablero, 1);
        $this->assertEquals(0, $this->orden->fresh()->overhead_cost);
    }

    public function test_costos_escritos_a_mano_se_respetan_hasta_que_hay_registros(): void
    {
        $admin = $this->admin();

        // Sin registros: los costos estimados que escribe el administrador se guardan.
        $this->actingAs($admin)->put("/work-orders/{$this->orden->id}", ['materials_cost' => 5000, 'labor_cost' => 2000, 'overhead_cost' => 700]);
        $o = $this->orden->fresh();
        $this->assertEquals([5000, 2000, 700], [$o->materials_cost, $o->labor_cost, $o->overhead_cost]);

        // Con registros: el sistema calcula y los valores manuales se ignoran.
        $this->usar($admin, $this->tablero, 1);
        $this->actingAs($admin)->put("/work-orders/{$this->orden->id}", ['materials_cost' => 9999, 'quoted_price' => 30000]);
        $o = $this->orden->fresh();
        $this->assertEquals(650, $o->materials_cost);
        $this->assertEquals(30000, $o->quoted_price);   // el precio sí se puede editar
    }

    public function test_cambiar_horas_de_maquina_recalcula_el_cif(): void
    {
        OverheadRate::create(['name' => 'Maquina', 'rate_type' => 'por_hora_maquina', 'value' => 20, 'is_active' => true]);
        $admin = $this->admin();
        $this->usar($admin, $this->tablero, 1);
        $this->assertEquals(0, $this->orden->fresh()->overhead_cost);

        $this->actingAs($admin)->put("/work-orders/{$this->orden->id}", ['machine_hours' => 5]);

        $this->assertEquals(100, $this->orden->fresh()->overhead_cost);
    }

    // ───────────────────────────── Movimientos de inventario ─────────────────────────────

    public function test_entrada_del_admin_con_costo_actualiza_el_promedio_ponderado(): void
    {
        // 15 piezas a $650 + 5 a $750  →  (15×650 + 5×750) / 20 = 675
        $this->actingAs($this->admin())->post('/inventory/movement', [
            'item_id' => $this->tablero->id, 'location_id' => $this->bodega->id,
            'movement_type' => 'entrada', 'quantity' => 5, 'unit_cost' => 750,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20.0, $this->stock($this->tablero));
        $this->assertEquals(675, $this->tablero->fresh()->unit_cost);
        $this->assertEquals(750, InventoryMovement::firstOrFail()->unit_cost);
    }

    public function test_entrada_del_operario_no_puede_cambiar_el_costo(): void
    {
        $this->actingAs($this->operario())->post('/inventory/movement', [
            'item_id' => $this->tablero->id, 'location_id' => $this->bodega->id,
            'movement_type' => 'entrada', 'quantity' => 5, 'unit_cost' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20.0, $this->stock($this->tablero));
        $this->assertEquals(650, $this->tablero->fresh()->unit_cost);
        $this->assertEquals(650, InventoryMovement::firstOrFail()->unit_cost);
    }

    public function test_salida_y_merma_no_pueden_dejar_el_stock_negativo(): void
    {
        $op = $this->operario();
        $base = ['item_id' => $this->tablero->id, 'location_id' => $this->bodega->id, 'quantity' => 16];

        $this->actingAs($op)->post('/inventory/movement', $base + ['movement_type' => 'salida'])->assertSessionHasErrors('quantity');
        $this->actingAs($op)->post('/inventory/movement', $base + ['movement_type' => 'merma'])->assertSessionHasErrors('quantity');
        $this->assertSame(15.0, $this->stock($this->tablero));

        // Una merma dentro de lo disponible sí se aplica y descuenta el stock
        $this->actingAs($op)->post('/inventory/movement', ['quantity' => 1, 'movement_type' => 'merma'] + $base)->assertSessionHasNoErrors();
        $this->assertSame(14.0, $this->stock($this->tablero));
        $this->assertSame('merma', InventoryMovement::firstOrFail()->movement_type);
    }

    public function test_tipos_de_movimiento_no_soportados_se_rechazan(): void
    {
        foreach (['ajuste', 'traspaso', 'inventado'] as $tipo) {
            $this->actingAs($this->admin())->post('/inventory/movement', [
                'item_id' => $this->tablero->id, 'location_id' => $this->bodega->id,
                'movement_type' => $tipo, 'quantity' => 1,
            ])->assertSessionHasErrors('movement_type');
        }
        $this->assertSame(0, InventoryMovement::count());
    }

    // ───────────────────────────── Datos financieros ocultos a operarios ─────────────────────────────

    private function prepararDatosConCostos(): void
    {
        OverheadRate::create(['name' => 'CIF', 'rate_type' => 'porcentaje_mano_obra', 'value' => 50, 'is_active' => true]);
        $admin = $this->admin(['hourly_rate' => 100]);
        $this->usar($admin, $this->tablero, 2);
        $this->actingAs($admin)->post("/work-orders/{$this->orden->id}/labor", ['hours' => 2]);
    }

    public function test_el_operario_no_recibe_ningun_dato_financiero(): void
    {
        $this->prepararDatosConCostos();

        $this->actingAs($this->operario())->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Dashboard')
            ->where('itemsData.0.cost', null)
            ->where('ordersData.0.quoted', null)
            ->where('ordersData.0.mat', null)
            ->where('ordersData.0.labor', null)
            ->where('ordersData.0.overhead', null)
            ->where('ordersData.0.machine_hours', null)
            ->where('ordersData.0.materials.0.cost', null)
            ->where('ordersData.0.materials.0.total', null)
            ->where('ordersData.0.labor_records.0.rate', null)
            ->where('ordersData.0.labor_records.0.total', null)
            ->where('movementsData.0.cost', null)
            ->where('hasOverheadRates', null)
            // lo operativo sí llega
            ->where('ordersData.0.materials.0.qty', 2)
            ->where('ordersData.0.labor_records.0.hours', 2));
    }

    public function test_la_respuesta_del_operario_no_contiene_los_importes_en_ninguna_parte(): void
    {
        $this->prepararDatosConCostos();

        $json = json_encode($this->actingAs($this->operario())->get('/')->viewData('page')['props']);

        foreach (['1300', '20000', '650', '25.5'] as $importe) {
            $this->assertStringNotContainsString($importe, $json, "El importe {$importe} no debe llegar a un operario");
        }
    }

    public function test_el_admin_si_recibe_los_datos_financieros(): void
    {
        $this->prepararDatosConCostos();

        $this->actingAs($this->admin())->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('itemsData.0.cost', fn ($c) => $c > 0)
            ->where('ordersData.0.quoted', 20000)
            ->where('ordersData.0.mat', 1300)
            ->where('ordersData.0.labor', 200)
            ->where('ordersData.0.overhead', 100)
            ->where('ordersData.0.materials.0.cost', 650)
            ->where('ordersData.0.labor_records.0.rate', 100)
            ->where('hasOverheadRates', true));
    }
}

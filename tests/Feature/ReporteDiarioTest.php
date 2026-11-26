<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MovementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReporteDiarioTest extends TestCase
{
    use RefreshDatabase;

    private Item $tablero;
    private Location $bodega;
    private WorkOrder $orden1;
    private WorkOrder $orden2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-12-09 10:00:00'));   // miércoles

        $this->bodega  = Location::create(['name' => 'Bodega', 'is_active' => true]);
        $this->tablero = Item::create(['name' => 'Tablero', 'sku' => 'T-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 100]);
        $this->orden1  = WorkOrder::create(['code' => 'OT-1', 'client_name' => 'Cliente', 'description' => 'Mueble', 'status' => 'en_proceso']);
        $this->orden2  = WorkOrder::create(['code' => 'OT-2', 'client_name' => 'Cliente', 'description' => 'Mueble', 'status' => 'en_proceso']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mov(string $type, string $when, ?string $refType = null, ?int $refId = null): void
    {
        $m = InventoryMovement::create([
            'item_id' => $this->tablero->id, 'location_id' => $this->bodega->id,
            'movement_type' => $type, 'quantity' => 1, 'unit_cost' => 100,
            'reference_type' => $refType ?? 'manual', 'reference_id' => $refId,
        ]);
        $m->forceFill(['created_at' => Carbon::parse($when)])->save();
    }

    private function dia(string $fecha): array
    {
        return collect(app(MovementReportService::class)->daily(14))->firstWhere('day', $fecha);
    }

    public function test_cuenta_los_movimientos_de_cada_dia_por_tipo(): void
    {
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden1->id);
        $this->mov('salida', '2026-12-08 15:00', 'work_order', $this->orden1->id);
        $this->mov('salida', '2026-12-08 16:00', 'work_order', $this->orden2->id);
        $this->mov('salida', '2026-12-08 17:00');                         // manual
        $this->mov('merma', '2026-12-08 17:30');
        $this->mov('devolucion', '2026-12-08 18:00', 'work_order', $this->orden1->id);
        $this->mov('entrada', '2026-12-09 08:00');

        $martes = $this->dia('2026-12-08');
        $this->assertSame(4, $martes['salidas']);
        $this->assertSame(3, $martes['salidas_orden']);
        $this->assertSame(1, $martes['salidas_manuales']);
        $this->assertSame(1, $martes['mermas']);
        $this->assertSame(1, $martes['devoluciones']);
        $this->assertSame(0, $martes['entradas']);
        $this->assertSame(2, $martes['ordenes'], 'cada orden cuenta una vez');

        $this->assertSame(1, $this->dia('2026-12-09')['entradas']);
    }

    public function test_incluye_los_dias_vacios_hasta_hoy_en_orden(): void
    {
        $dias = app(MovementReportService::class)->daily(14);

        $this->assertCount(14, $dias);
        $this->assertSame('2026-11-26', $dias[0]['day']);
        $this->assertSame('2026-12-09', $dias[13]['day'], 'el último es hoy');
        $this->assertSame(0, array_sum(array_column($dias, 'salidas')));
    }

    public function test_un_movimiento_de_medianoche_cae_en_su_dia(): void
    {
        $this->mov('salida', '2026-12-07 23:59');
        $this->mov('salida', '2026-12-08 00:01');

        $this->assertSame(1, $this->dia('2026-12-07')['salidas']);
        $this->assertSame(1, $this->dia('2026-12-08')['salidas']);
    }

    public function test_el_numero_de_dias_se_limita(): void
    {
        $this->assertCount(1, app(MovementReportService::class)->daily(0));
        $this->assertCount(MovementReportService::MAX_DAYS, app(MovementReportService::class)->daily(5000));
    }

    public function test_el_reporte_diario_no_trae_importes_ni_nombres(): void
    {
        $this->assertSame(
            ['day', 'salidas', 'salidas_orden', 'salidas_manuales', 'mermas', 'devoluciones', 'entradas', 'ordenes'],
            array_keys($this->dia('2026-12-09'))
        );
    }

    public function test_solo_el_admin_lo_recibe_y_lo_descarga(): void
    {
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden1->id);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('dailyMovements', 14)->where('dailyMovements.12.salidas', 1));

        $operario = User::factory()->operario()->create();
        $this->actingAs($operario)->get('/')->assertInertia(fn (Assert $p) => $p->where('dailyMovements', null));
        $this->actingAs($operario)->get('/reports/movimientos-diarios.csv')->assertForbidden();
    }

    public function test_el_admin_descarga_el_csv_diario(): void
    {
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden1->id);

        $resp = $this->actingAs(User::factory()->admin()->create())->get('/reports/movimientos-diarios.csv?dias=3');

        $resp->assertOk();
        $lineas = array_values(array_filter(explode("\n", $resp->streamedContent())));
        $this->assertCount(4, $lineas, 'encabezado y 3 días');
        $this->assertStringContainsString('Día', $lineas[0]);
        $this->assertStringStartsWith('2026-12-07,0,0,0', $lineas[1]);
        $this->assertStringStartsWith('2026-12-08,1,1,0', $lineas[2]);
    }
}

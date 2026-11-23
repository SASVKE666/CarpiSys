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

class ReporteSemanalTest extends TestCase
{
    use RefreshDatabase;

    private Item $tablero;
    private Location $bodega;
    private WorkOrder $orden1;
    private WorkOrder $orden2;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles 9 de diciembre de 2026: la semana actual empieza el lunes 7.
        Carbon::setTestNow(Carbon::parse('2026-12-09 10:00:00'));

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

    private function semana(string $lunes): array
    {
        return collect(app(MovementReportService::class)->weekly(12))->firstWhere('week', $lunes);
    }

    public function test_cuenta_los_movimientos_de_la_semana_por_tipo(): void
    {
        // Lunes 7 a domingo 13 de diciembre
        $this->mov('salida', '2026-12-07 09:00', 'work_order', $this->orden1->id);
        $this->mov('salida', '2026-12-07 15:00', 'work_order', $this->orden1->id);
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden2->id);
        $this->mov('salida', '2026-12-09 08:00');                       // manual, sin orden
        $this->mov('merma', '2026-12-09 08:30');
        $this->mov('devolucion', '2026-12-09 09:00', 'work_order', $this->orden1->id);
        $this->mov('entrada', '2026-12-09 09:30');

        $w = $this->semana('2026-12-07');

        $this->assertSame(4, $w['salidas']);
        $this->assertSame(3, $w['salidas_orden']);
        $this->assertSame(1, $w['salidas_manuales']);
        $this->assertSame(1, $w['mermas']);
        $this->assertSame(1, $w['devoluciones']);
        $this->assertSame(1, $w['entradas']);
        $this->assertSame(2, $w['ordenes'], 'OT-1 y OT-2 cuentan una sola vez cada una');
        $this->assertSame(3, $w['dias_con_salidas'], 'salidas el 7, 8 y 9');
    }

    public function test_el_domingo_cuenta_en_su_semana_y_el_lunes_abre_la_siguiente(): void
    {
        $this->mov('salida', '2026-12-06 23:30');   // domingo de la semana anterior
        $this->mov('salida', '2026-12-07 00:30');   // lunes de la semana actual

        $this->assertSame(1, $this->semana('2026-11-30')['salidas']);
        $this->assertSame(1, $this->semana('2026-12-07')['salidas']);
    }

    public function test_incluye_las_semanas_vacias_en_orden_y_con_lunes(): void
    {
        $semanas = app(MovementReportService::class)->weekly(12);

        $this->assertCount(12, $semanas);
        $this->assertSame('2026-12-07', $semanas[11]['week'], 'la última es la semana actual');
        $this->assertSame('2026-09-21', $semanas[0]['week']);
        foreach ($semanas as $s) {
            $this->assertSame(1, Carbon::parse($s['week'])->dayOfWeekIso, 'cada semana empieza en lunes');
            $this->assertSame(0, $s['salidas']);
        }
    }

    public function test_ignora_lo_anterior_al_periodo(): void
    {
        $this->mov('salida', '2026-01-05 10:00');

        $this->assertSame(0, array_sum(array_column(app(MovementReportService::class)->weekly(12), 'salidas')));
    }

    public function test_el_numero_de_semanas_se_limita(): void
    {
        $this->assertCount(1, app(MovementReportService::class)->weekly(0));
        $this->assertCount(52, app(MovementReportService::class)->weekly(500));
    }

    public function test_el_administrador_recibe_el_reporte_en_el_dashboard(): void
    {
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden1->id);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('weeklyMovements', 12)
                ->where('weeklyMovements.11.week', '2026-12-07')
                ->where('weeklyMovements.11.salidas', 1));
    }

    public function test_el_reporte_no_trae_importes_ni_nombres(): void
    {
        $this->mov('salida', '2026-12-08 09:00');

        $fila = app(MovementReportService::class)->weekly(1)[0];

        $this->assertSame(
            ['week', 'salidas', 'salidas_orden', 'salidas_manuales', 'mermas', 'devoluciones', 'entradas', 'ordenes', 'dias_con_salidas'],
            array_keys($fila)
        );
    }

    public function test_el_operario_no_recibe_el_reporte_ni_puede_descargarlo(): void
    {
        $operario = User::factory()->operario()->create();

        $this->actingAs($operario)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('weeklyMovements', null));

        $this->actingAs($operario)->get('/reports/movimientos-semanales.csv')->assertForbidden();
    }

    public function test_el_administrador_descarga_el_csv(): void
    {
        $this->mov('salida', '2026-12-08 09:00', 'work_order', $this->orden1->id);

        $resp = $this->actingAs(User::factory()->admin()->create())
            ->get('/reports/movimientos-semanales.csv?semanas=3');

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));

        $lineas = array_values(array_filter(explode("\n", $resp->streamedContent())));
        $this->assertCount(4, $lineas, 'encabezado y 3 semanas');
        $this->assertStringContainsString('Semana (lunes)', $lineas[0]);
        $this->assertStringStartsWith('2026-11-23,0,0,0', $lineas[1]);
        $this->assertStringStartsWith('2026-12-07,1,1,0', $lineas[3]);
    }
}

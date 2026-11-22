<?php

namespace Tests\Feature;

use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\Location;
use App\Models\OverheadRate;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasasYTarifasTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_el_admin_ve_y_edita_las_tasas_cif(): void
    {
        $op = User::factory()->operario()->create();
        $this->actingAs($op)->get('/overhead-rates')->assertForbidden();
        $this->actingAs($op)->post('/overhead-rates', ['name' => 'X', 'rate_type' => 'porcentaje_mano_obra', 'value' => 10])->assertForbidden();
        $this->assertSame(0, OverheadRate::count());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/overhead-rates')->assertOk();
        $this->actingAs($admin)->post('/overhead-rates', ['name' => 'Luz', 'rate_type' => 'porcentaje_mano_obra', 'value' => 25])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, OverheadRate::where('name', 'Luz')->where('is_active', true)->count());
    }

    public function test_validacion_de_tasas(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/overhead-rates', ['name' => 'X', 'rate_type' => 'monto_fijo_mensual', 'value' => 100])
            ->assertSessionHasErrors('rate_type');   // este tipo no se reparte automáticamente
        $this->actingAs($admin)->post('/overhead-rates', ['name' => 'X', 'rate_type' => 'porcentaje_materiales', 'value' => -1])
            ->assertSessionHasErrors('value');
        $this->actingAs($admin)->post('/overhead-rates', ['name' => 'X', 'rate_type' => 'porcentaje_materiales', 'value' => 900])
            ->assertSessionHasErrors('value');
        $this->assertSame(0, OverheadRate::count());
    }

    public function test_cambiar_una_tasa_recalcula_las_ordenes_abiertas_y_respeta_las_cerradas(): void
    {
        $admin = User::factory()->admin()->create(['hourly_rate' => 100]);
        $this->actingAs($admin);

        $rate = OverheadRate::create(['name' => 'Luz', 'rate_type' => 'porcentaje_mano_obra', 'value' => 10, 'is_active' => true]);
        $abierta = WorkOrder::create(['client_name' => 'A', 'description' => 'a']);
        $cerrada = WorkOrder::create(['client_name' => 'B', 'description' => 'b']);
        foreach ([$abierta, $cerrada] as $o) {
            $this->post("/work-orders/{$o->id}/labor", ['hours' => 10]);   // MOD = 1000
        }
        $cerrada->update(['status' => 'completada']);
        $this->assertEquals(100, $abierta->fresh()->overhead_cost);

        $this->put("/overhead-rates/{$rate->id}", ['value' => 30])->assertSessionHasNoErrors();

        $this->assertEquals(300, $abierta->fresh()->overhead_cost);
        $this->assertEquals(100, $cerrada->fresh()->overhead_cost);   // cerrada: conserva su costo

        $this->put("/overhead-rates/{$rate->id}", ['is_active' => false]);
        $this->assertEquals(0, $abierta->fresh()->overhead_cost);
    }

    public function test_orden_con_costos_manuales_no_se_toca_al_cambiar_tasas(): void
    {
        $admin = User::factory()->admin()->create();
        $rate = OverheadRate::create(['name' => 'Luz', 'rate_type' => 'porcentaje_mano_obra', 'value' => 10, 'is_active' => true]);
        $manual = WorkOrder::create(['client_name' => 'M', 'description' => 'm', 'overhead_cost' => 777]);

        $this->actingAs($admin)->put("/overhead-rates/{$rate->id}", ['value' => 50]);

        $this->assertEquals(777, $manual->fresh()->overhead_cost);
    }

    public function test_el_admin_define_la_tarifa_por_hora_de_un_usuario(): void
    {
        $admin = User::factory()->admin()->create();
        $op = User::factory()->operario()->create();

        $this->actingAs($admin)->put("/users/{$op->id}", ['hourly_rate' => 62.5])->assertSessionHasNoErrors();
        $this->assertEquals(62.5, $op->fresh()->hourly_rate);

        $this->actingAs($admin)->put("/users/{$op->id}", ['hourly_rate' => -5])->assertSessionHasErrors('hourly_rate');
        $this->actingAs($admin)->put("/users/{$op->id}", ['role' => 'operario']);   // sin hourly_rate: no la borra
        $this->assertEquals(62.5, $op->fresh()->hourly_rate);
    }

    public function test_cambiar_la_tarifa_no_altera_horas_ya_registradas(): void
    {
        $admin = User::factory()->admin()->create();
        $op = User::factory()->operario()->create(['hourly_rate' => 50]);
        $orden = WorkOrder::create(['client_name' => 'A', 'description' => 'a']);

        $this->actingAs($op)->post("/work-orders/{$orden->id}/labor", ['hours' => 2]);
        $this->actingAs($admin)->put("/users/{$op->id}", ['hourly_rate' => 500]);

        $this->assertEquals(100, $orden->fresh()->labor_cost);
        $this->actingAs($op->fresh())->post("/work-orders/{$orden->id}/labor", ['hours' => 1]);
        $this->assertEquals(100 + 500, $orden->fresh()->labor_cost);
    }

    public function test_un_operario_no_puede_cambiar_su_tarifa_ni_la_de_otros(): void
    {
        $op = User::factory()->operario()->create(['hourly_rate' => 50]);

        $this->actingAs($op)->put("/users/{$op->id}", ['hourly_rate' => 9999])->assertForbidden();
        $this->actingAs($op)->patch('/profile', ['name' => 'X', 'email' => $op->email, 'hourly_rate' => 9999]);

        $this->assertEquals(50, $op->fresh()->hourly_rate);
    }
}

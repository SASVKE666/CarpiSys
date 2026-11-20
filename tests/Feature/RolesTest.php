<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function orden(): WorkOrder
    {
        return WorkOrder::create([
            'client_name'  => 'Cliente',
            'description'  => 'Mueble',
            'quoted_price' => 1000,
        ]);
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_usuario_desactivado_con_sesion_abierta_es_expulsado(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operario_no_entra_a_la_gestion_de_usuarios(): void
    {
        $this->actingAs(User::factory()->operario()->create())
            ->get('/users')->assertForbidden();
    }

    public function test_admin_crea_usuarios_con_rol(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/users', [
                'name' => 'Juan', 'email' => 'juan@example.com',
                'password' => 'password123', 'role' => 'operario',
            ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com', 'role' => 'operario', 'is_active' => true,
        ]);
    }

    public function test_admin_no_puede_quitarse_su_propio_rol_ni_desactivarse(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", ['role' => 'operario'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin)->put("/users/{$admin->id}", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_el_rol_no_se_puede_cambiar_desde_el_perfil(): void
    {
        $user = User::factory()->operario()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Otro', 'email' => $user->email, 'role' => 'admin',
        ]);

        $this->assertSame('operario', $user->fresh()->role);
    }

    public function test_operario_no_puede_crear_ni_borrar_articulos(): void
    {
        $operario = User::factory()->operario()->create();
        $item = Item::create(['name' => 'Tabla', 'sku' => 'T-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 10]);

        $this->actingAs($operario)->post('/items', [
            'name' => 'Nuevo', 'sku' => 'N-1', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 5,
        ])->assertForbidden();
        $this->actingAs($operario)->delete("/items/{$item->id}")->assertForbidden();

        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_operario_solo_puede_cambiar_el_estado_de_la_orden(): void
    {
        $operario = User::factory()->operario()->create();
        $orden = $this->orden();

        $this->actingAs($operario)->put("/work-orders/{$orden->id}", ['status' => 'en_proceso'])
            ->assertRedirect();
        $this->assertSame('en_proceso', $orden->fresh()->status);

        $this->actingAs($operario)->put("/work-orders/{$orden->id}", ['status' => 'pausada', 'quoted_price' => 1])
            ->assertForbidden();
        $this->assertEquals(1000, $orden->fresh()->quoted_price);
        $this->assertSame('en_proceso', $orden->fresh()->status);

        $this->actingAs($operario)->post('/work-orders', ['client_name' => 'A', 'description' => 'B'])
            ->assertForbidden();
    }

    public function test_admin_edita_precios_de_la_orden(): void
    {
        $orden = $this->orden();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/work-orders/{$orden->id}", ['quoted_price' => 2500])
            ->assertRedirect();

        $this->assertEquals(2500, $orden->fresh()->quoted_price);
        $this->assertSame(1, WorkOrder::count());
    }

    public function test_rutas_exigen_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
    }
}

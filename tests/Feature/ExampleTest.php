<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_el_dashboard_carga_para_un_usuario_con_sesion(): void
    {
        $this->actingAs(User::factory()->operario()->create())
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('itemsData')
                ->has('invData')
                ->has('ordersData')
                ->where('auth.user.role', 'operario'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_de_login_no_padrao_adminlte(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('class="login-page', false)
            ->assertSee('Lembrar-me')
            ->assertSee('Esqueci minha senha')
            ->assertDontSee('admin@argon.com')
            ->assertDontSee('ni ni-', false);
    }

    public function test_login_invalido_mostra_erro_em_portugues(): void
    {
        $this->from(route('login'))
            ->post(route('login'), ['email' => 'ninguem@example.com', 'password' => 'errada'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_dashboard_mostra_topo_e_menu_da_aplicacao(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee('Sair')
            ->assertSee('Dashboard')
            ->assertDontSee('Notifications')
            ->assertDontSee('AdminLTE v4')
            ->assertDontSee('apexcharts')
            ->assertSee('--bs-primary: #0d6efd', false);
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use App\View\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sem_permissoes_ve_so_o_dashboard(): void
    {
        $menu = Menu::fromConfig()->itensVisiveis(User::factory()->create());

        $this->assertSame(['Dashboard'], array_column($menu, 'titulo'));
    }

    public function test_com_user_list_ve_usuarios_e_nao_perfis(): void
    {
        $user = $this->usuarioCom('user-list');

        $menu = Menu::fromConfig()->itensVisiveis($user);

        $this->assertSame(['Dashboard', 'Configurações'], array_column($menu, 'titulo'));
        $this->assertSame(['Usuários'], array_column($menu[1]['itens'], 'titulo'));
        $this->assertSame(route('user.index'), $menu[1]['itens'][0]['url']);
    }

    public function test_item_com_rota_inexistente_nao_aparece(): void
    {
        $user = $this->usuarioCom('user-list');

        $menu = (new Menu([
            ['titulo' => 'Inexistente', 'rota' => 'rota.que.nao.existe', 'permissao' => 'user-list'],
            ['titulo' => 'Grupo vazio', 'itens' => [
                ['titulo' => 'Também inexistente', 'rota' => 'outra.inexistente'],
            ]],
            ['titulo' => 'Usuários', 'rota' => 'user.index', 'permissao' => 'user-list'],
        ], app('router')))->itensVisiveis($user);

        $this->assertSame(['Usuários'], array_column($menu, 'titulo'));
    }

    public function test_marca_item_e_grupo_da_rota_atual_como_ativos(): void
    {
        $user = $this->usuarioCom('user-list', 'grupo-list');
        $this->actingAs($user)->get(route('user.index'));

        $configuracoes = collect(Menu::fromConfig()->itensVisiveis($user))->firstWhere('titulo', 'Configurações');
        $ativos = array_column($configuracoes['itens'], 'ativo', 'titulo');

        $this->assertTrue($configuracoes['ativo']);
        $this->assertTrue($ativos['Usuários']);
        $this->assertFalse($ativos['Perfis']);
    }
}

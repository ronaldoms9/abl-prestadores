<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjudaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_perfil_ve_so_os_manuais_que_cabem_a_ele(): void
    {
        $this->actingAs($this->usuarioCom('portal-acessar'))->get(route('ajuda.index'))
            ->assertOk()->assertSee('Manual do colaborador')->assertDontSee('Manual do RH')->assertDontSee('Guia de operação');

        $this->actingAs($this->usuarioCom('equipe-gerir'))->get(route('ajuda.show', 'gestor'))
            ->assertOk()->assertSee('Manual do gestor')->assertSee('Aprovar férias');
        $this->get(route('ajuda.show', 'rh'))->assertForbidden();
        $this->get(route('ajuda.show', 'inexistente'))->assertNotFound();

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('ajuda.index'))->assertOk()->assertSee('Nenhum manual disponível');
    }

    public function test_tabelas_do_markdown_viram_tabelas_html(): void
    {
        $this->actingAs($this->usuarioCom('fechamento-reabrir'))->get(route('ajuda.show', 'operacao'))
            ->assertOk()->assertSee('<table>', false)->assertSee('notas.prazo_dia');
    }

    public function test_todos_os_manuais_existem_e_nao_citam_telas_inexistentes(): void
    {
        foreach (['rh', 'gestor', 'financeiro', 'colaborador', 'operacao'] as $slug) {
            $this->assertFileExists(base_path("docs/manual/{$slug}.md"));
        }
        // Os nomes de menu citados nos manuais precisam existir em config/menu.php.
        $menu = json_encode(config('menu'), JSON_UNESCAPED_UNICODE);
        foreach (['Fechamento mensal', 'Notas fiscais', 'Relatórios', 'Aditivos pendentes', 'Tabela de benefícios', 'Reajuste de benefícios', 'Solicitações de férias', 'Calendário', 'Carregar planilha', 'Integrações'] as $item) {
            $this->assertStringContainsString($item, $menu, "{$item} não está no menu");
        }
    }
}

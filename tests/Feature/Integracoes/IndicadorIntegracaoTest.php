<?php

namespace Tests\Feature\Integracoes;

use App\Models\IntegracaoLog;
use App\Models\Sincronizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IndicadorIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_a_permissao_o_sino_nao_aparece(): void
    {
        $this->actingAs($this->usuarioCom('user-list'))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('indicador-integracao');
    }

    public function test_conta_so_falhas_sem_solucao_das_ultimas_24_horas(): void
    {
        IntegracaoLog::factory()->erro()->create(['operacao' => 'pessoa-por-documento', 'chave_idempotencia' => 'pessoa:1', 'executado_em' => now()->subHours(2)]);
        IntegracaoLog::factory()->erro()->create(['operacao' => 'obras-ativas', 'executado_em' => now()->subHours(5)]);
        // Resolvida por um sucesso posterior.
        IntegracaoLog::factory()->erro()->create(['operacao' => 'empresas-ativas', 'executado_em' => now()->subHours(3)]);
        IntegracaoLog::factory()->sucesso()->create(['operacao' => 'empresas-ativas', 'executado_em' => now()->subHour()]);
        // Mais antiga que 24 h.
        IntegracaoLog::factory()->erro()->create(['operacao' => 'pessoa-por-documento', 'chave_idempotencia' => 'pessoa:2', 'executado_em' => now()->subHours(25)]);

        $this->actingAs($this->usuarioCom('integracao-list'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('indicador-integracao')
            ->assertSee('<span class="navbar-badge badge text-bg-danger">2</span>', false)
            ->assertSee('2 falhas sem solução nas últimas 24 h')
            ->assertSee('Última sincronização concluída:')
            ->assertSee('nenhuma');
    }

    public function test_mostra_a_ultima_sincronizacao_concluida(): void
    {
        Sincronizacao::factory()->create(['concluida_em' => now()->subDays(2)->setTime(5, 3)]);
        Sincronizacao::factory()->create(['concluida_em' => now()->subDay()->setTime(5, 4)]);
        Sincronizacao::factory()->emAndamento()->create();

        $this->actingAs($this->usuarioCom('integracao-list'))
            ->get(route('home'))
            ->assertSee('Nenhuma falha sem solução nas últimas 24 h')
            ->assertDontSee('navbar-badge')
            ->assertSee(now()->subDay()->setTime(5, 4)->format('d/m/Y H:i'));
    }

    public function test_concluir_uma_sincronizacao_limpa_o_cache(): void
    {
        $usuario = $this->usuarioCom('integracao-list');
        $this->actingAs($usuario)->get(route('home'))->assertSee('nenhuma');
        $this->assertTrue(Cache::has(Sincronizacao::CACHE_INDICADOR));

        $this->travelTo(now()->setTime(5, 7));
        Sincronizacao::factory()->emAndamento()->create()->concluir();

        $this->assertFalse(Cache::has(Sincronizacao::CACHE_INDICADOR));
        $this->actingAs($usuario)->get(route('home'))->assertSee(now()->format('d/m/Y').' 05:07');
    }
}

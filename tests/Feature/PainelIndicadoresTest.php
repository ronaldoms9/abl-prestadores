<?php

namespace Tests\Feature;

use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Competencia;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\Fechamento;
use App\Models\IntegracaoLog;
use App\Models\NotaFiscal;
use App\Models\Obra;
use App\Services\Painel\IndicadoresPainel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelIndicadoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ativo(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
    }

    public function test_cada_bloco_so_aparece_para_quem_tem_a_permissao(): void
    {
        $this->assertSame([], app(IndicadoresPainel::class)->para($this->usuarioCom('qualquer-coisa'), now()));

        $rh = app(IndicadoresPainel::class)->para($this->usuarioCom('colaborador-list', 'contrato-list', 'ferias-list'), now());
        $this->assertEqualsCanonicalizing(['movimento', 'sem_equipe', 'por_obra', 'contratos', 'ferias'], array_keys($rh));

        $financeiro = app(IndicadoresPainel::class)->para($this->usuarioCom('fechamento-list', 'nota-list', 'integracao-list'), now());
        $this->assertEqualsCanonicalizing(['custo', 'notas_pendentes', 'integracoes_com_erro'], array_keys($financeiro));
    }

    public function test_movimento_sem_equipe_e_ativos_por_obra(): void
    {
        $obra = Obra::factory()->create(['descricao' => 'Torre Jardins']);
        $comEquipe = $this->ativo(['obra_id' => $obra->id]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $comEquipe->id, 'equipe_id' => Equipe::factory()->create()->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $this->ativo(['obra_id' => $obra->id]);
        $this->ativo(['data_admissao' => '2026-10-03']);
        $this->ativo(['status' => StatusColaborador::Desligado, 'data_desligamento' => '2026-10-09']);

        $d = app(IndicadoresPainel::class)->para($this->usuarioCom('colaborador-list'), now());

        $this->assertSame(['admissoes' => 1, 'desligamentos' => 1], $d['movimento']);
        $this->assertSame(2, $d['sem_equipe'], 'Três ativos, um deles com equipe');
        $this->assertSame([['obra' => 'Torre Jardins', 'total' => 2], ['obra' => 'Sem obra', 'total' => 1]], $d['por_obra']);
    }

    public function test_gestor_ve_so_os_numeros_das_equipes_que_gere(): void
    {
        $gestor = $this->usuarioCom('colaborador-list', 'equipe-gerir');
        $meu = $this->ativo();
        $equipe = Equipe::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => null]);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $this->ativo();

        $d = app(IndicadoresPainel::class)->para($gestor, now());

        $this->assertSame([['obra' => 'Sem obra', 'total' => 1]], $d['por_obra']);
        $this->assertSame(0, $d['sem_equipe']);
    }

    public function test_custo_so_conta_competencias_aprovadas_e_traz_a_serie(): void
    {
        foreach (['2026-07' => 1000, '2026-08' => 2000, '2026-09' => 3000] as $mes => $valor) {
            Fechamento::factory()->create(['competencia_id' => Competencia::factory()->create(['ano_mes' => $mes, 'status' => 'aprovada'])->id, 'total_liquido' => $valor]);
        }
        Fechamento::factory()->create(['competencia_id' => Competencia::factory()->create(['ano_mes' => '2026-10', 'status' => 'em_conferencia'])->id, 'total_liquido' => 99999]);

        $c = app(IndicadoresPainel::class)->para($this->usuarioCom('fechamento-list'), now())['custo'];

        $this->assertSame(['09/2026', 3000.0], [$c['ultimo_mes'], $c['ultimo_total']]);
        $this->assertSame(['07/2026', '08/2026', '09/2026'], array_column($c['serie'], 'mes'));
    }

    public function test_notas_pendentes_e_integracoes_com_erro(): void
    {
        $competencia = Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada']);
        Fechamento::factory()->create(['competencia_id' => $competencia->id, 'status' => StatusFechamento::AguardandoNota]); // sem nota
        $recusada = Fechamento::factory()->create(['competencia_id' => $competencia->id, 'status' => StatusFechamento::AguardandoNota]);
        NotaFiscal::factory()->create(['fechamento_id' => $recusada->id, 'status' => 'recusada']);
        $ok = Fechamento::factory()->create(['competencia_id' => $competencia->id, 'status' => StatusFechamento::AguardandoNota]);
        NotaFiscal::factory()->create(['fechamento_id' => $ok->id, 'status' => 'enviada']);
        IntegracaoLog::factory()->create(['status' => 'erro']);
        IntegracaoLog::factory()->create(['status' => 'sucesso']);

        $d = app(IndicadoresPainel::class)->para($this->usuarioCom('nota-list', 'integracao-list'), now());

        $this->assertSame([2, 1], [$d['notas_pendentes'], $d['integracoes_com_erro']]);
    }

    public function test_dashboard_renderiza_os_novos_blocos_e_o_grafico(): void
    {
        Fechamento::factory()->create(['competencia_id' => Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada'])->id, 'total_liquido' => 4321]);
        $this->ativo();

        $this->actingAs($this->usuarioCom('colaborador-list', 'fechamento-list'))->get(route('home'))
            ->assertOk()->assertSee('Movimento do mês')->assertSee('R$ 4.321,00')->assertSee('grafico-custo', false)->assertSee('Ativos por obra');
    }
}

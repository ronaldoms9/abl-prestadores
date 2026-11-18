<?php

namespace Tests\Feature;

use App\Actions\Fechamento\AbrirCompetencia;
use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Competencia;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\Fechamento;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\NotaFiscal;
use App\Models\Obra;
use App\Models\Remuneracao;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Consultas repetidas (N+1) nas telas que crescem com o número de colaboradores
 * (E6, fatia 4). Cada tela é medida com poucos e com muitos registros: o número
 * de consultas não pode crescer junto com os dados.
 */
class DesempenhoConsultasTest extends TestCase
{
    use RefreshDatabase;

    /** Quanto o número de consultas pode crescer ao triplicar os registros (folga para paginação e cache). */
    private const FOLGA = 6;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
        FeriasNivel::create(['ordem' => 1, 'tipo_aprovador' => 'gestor_equipe', 'ativo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function consultas(callable $acao): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $acao();

        return $n;
    }

    /** Acrescenta colaboradores ativos, cada um com obra, equipe, período de férias, fechamento e nota. */
    private function popular(int $quantos, Competencia $competencia, Equipe $equipe, User $gestor): void
    {
        for ($i = 0; $i < $quantos; $i++) {
            $c = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01', 'obra_id' => Obra::factory()->create()->id]);
            Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3000, 'vigencia_inicio' => '2025-01-01']);
            ColaboradorEquipe::factory()->create(['colaborador_id' => $c->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => null]);
            $periodo = FeriasPeriodo::factory()->create(['colaborador_id' => $c->id]);
            FeriasSolicitacao::factory()->create(['colaborador_id' => $c->id, 'ferias_periodo_id' => $periodo->id, 'inicio' => '2026-11-02', 'fim' => '2026-11-11', 'dias' => 10, 'status' => 'pendente', 'solicitado_por' => $gestor->id]);
            $f = Fechamento::factory()->create(['colaborador_id' => $c->id, 'competencia_id' => $competencia->id, 'status' => StatusFechamento::AguardandoNota]);
            NotaFiscal::factory()->create(['fechamento_id' => $f->id, 'status' => 'divergente', 'divergencias' => [['regra' => 'valor', 'mensagem' => 'x']]]);
        }
    }

    /** @return array<string, array{0: string, 1: string[]}> */
    public static function telas(): array
    {
        return [
            'fechamento (competência)' => ['fechamento.show', ['fechamento-list']],
            'notas fiscais' => ['nota.index', ['nota-list']],
            'solicitações de férias' => ['ferias.index', ['ferias-list']],
            'relatório de custo' => ['relatorio.show:custo-por-obra', ['fechamento-list']],
            'relatório de férias' => ['relatorio.show:ferias-e-saldos', ['ferias-list']],
            'relatório de notas' => ['relatorio.show:notas-pendentes', ['nota-list']],
            'relatório de contratos' => ['relatorio.show:contratos-vencendo', ['contrato-list']],
            'painel' => ['home', ['colaborador-list', 'fechamento-list', 'nota-list', 'contrato-list', 'ferias-list']],
            'lista de colaboradores' => ['colaborador.index', ['colaborador-list']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('telas')]
    public function test_o_numero_de_consultas_nao_cresce_com_os_dados(string $alvo, array $permissoes): void
    {
        $competencia = Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada']);
        $equipe = Equipe::factory()->create();
        $gestor = $this->usuarioCom(...$permissoes);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $this->actingAs($gestor);
        [$rota, $slug] = array_pad(explode(':', $alvo, 2), 2, null);
        $url = match (true) {
            $rota === 'fechamento.show' => route('fechamento.show', $competencia->id),
            $slug !== null => route($rota, $slug),
            default => route($rota),
        };
        $abrir = fn () => $this->get($url)->assertOk();

        $this->popular(5, $competencia, $equipe, $gestor);
        $abrir(); // aquece caches e autoload
        $poucos = $this->consultas($abrir);

        $this->popular(15, $competencia, $equipe, $gestor);
        $muitos = $this->consultas($abrir);

        $this->assertLessThanOrEqual($poucos + self::FOLGA, $muitos, "{$alvo}: {$poucos} consultas com 5 colaboradores e {$muitos} com 20 (provável N+1).");
    }

    public function test_calcular_a_competencia_gasta_um_numero_limitado_de_consultas_por_colaborador(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $c = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
            Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3000, 'vigencia_inicio' => '2025-01-01']);
        }

        $consultas = $this->consultas(fn () => app(AbrirCompetencia::class)->executar('2026-09'));

        // Média por colaborador (inclui cálculo, itens e auditoria); um estouro aqui indica consulta nova dentro do laço.
        $this->assertLessThan(20 * 40, $consultas, "Abrir a competência de 20 colaboradores gastou {$consultas} consultas.");
    }
}

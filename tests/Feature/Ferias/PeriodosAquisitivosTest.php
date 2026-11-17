<?php

namespace Tests\Feature\Ferias;

use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\IniciarAdmissao;
use App\Actions\Ferias\AjustarSaldoFerias;
use App\Actions\Ferias\FeriasAVencer;
use App\Actions\Ferias\GerarPeriodosAquisitivos;
use App\Console\Commands\AlertasFerias;
use App\Enums\StatusColaborador;
use App\Models\AdmissaoChecklistItem;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\FeriasPeriodo;
use App\Models\Remuneracao;
use App\Models\User;
use App\Notifications\ResumoFerias;
use App\Support\AvisosAdmin;
use Carbon\Carbon;
use Database\Seeders\FeriasNivelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PeriodosAquisitivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 07:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ativo(string $admissao, array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => $admissao]);
    }

    private function gerar(Colaborador $c, ?string $hoje = null)
    {
        return app(GerarPeriodosAquisitivos::class)->executar($c, $hoje ? Carbon::parse($hoje) : null);
    }

    public function test_admitido_hoje_tem_o_primeiro_periodo_aberto_de_12_meses(): void
    {
        $c = $this->ativo('2026-11-18');

        $criados = $this->gerar($c);

        $p = $criados->sole();
        $this->assertSame(['2026-11-18', '2027-11-17', 30, 0, '2028-11-17'], [$p->aquisitivo_inicio->toDateString(), $p->aquisitivo_fim->toDateString(), $p->dias_direito, $p->dias_utilizados, $p->limite_gozo->toDateString()]);
        $this->assertTrue(Activity::where('event', 'ferias-periodos-gerados')->exists());
    }

    public function test_admissao_antiga_gera_todos_os_periodos_que_ja_comecaram_sem_folga(): void
    {
        $c = $this->ativo('2024-03-02');

        $criados = $this->gerar($c);

        $this->assertSame(
            [['2024-03-02', '2025-03-01'], ['2025-03-02', '2026-03-01'], ['2026-03-02', '2027-03-01']],
            $criados->map(fn ($p) => [$p->aquisitivo_inicio->toDateString(), $p->aquisitivo_fim->toDateString()])->all(),
        );
    }

    public function test_o_periodo_seguinte_abre_no_dia_em_que_o_anterior_completa(): void
    {
        $c = $this->ativo('2025-11-18');

        $this->assertCount(1, $this->gerar($c, '2026-11-17'), 'véspera: só o primeiro');
        $this->assertCount(0, $this->gerar($c, '2026-11-17'), 'rodar de novo não duplica');
        $novos = $this->gerar($c, '2026-11-18');
        $this->assertSame(['2026-11-18'], $novos->map(fn ($p) => $p->aquisitivo_inicio->toDateString())->all());
        $this->assertSame(2, $c->feriasPeriodos()->count());
    }

    public function test_admissao_em_29_de_fevereiro_nao_estoura_o_mes_e_os_periodos_continuam_colados(): void
    {
        $c = $this->ativo('2024-02-29');

        $periodos = $this->gerar($c, '2026-11-18')->values();

        $this->assertSame(['2024-02-29', '2025-02-28', '2026-02-28'], $periodos->map(fn ($p) => $p->aquisitivo_inicio->toDateString())->all());
        foreach ($periodos->slice(0, -1) as $i => $p) {
            $this->assertSame($periodos[$i + 1]->aquisitivo_inicio->toDateString(), $p->aquisitivo_fim->copy()->addDay()->toDateString(), 'sem folga nem sobreposição');
        }
    }

    public function test_dias_de_direito_e_limite_de_gozo_vem_da_configuracao(): void
    {
        Configuracao::definir('ferias.dias_direito', 20);
        Configuracao::definir('ferias.limite_gozo_meses', 6);
        $c = $this->ativo('2026-01-05');

        $p = $this->gerar($c)->sole();

        $this->assertSame([20, '2027-07-04'], [$p->dias_direito, $p->limite_gozo->toDateString()]);
    }

    public function test_configuracao_invalida_cai_no_padrao_e_o_direito_ja_gravado_nao_muda(): void
    {
        Configuracao::definir('ferias.dias_direito', 'muitos');
        $c = $this->ativo('2025-01-05');
        $this->assertSame(30, $this->gerar($c)->first()->dias_direito);

        Configuracao::definir('ferias.dias_direito', 25);
        $this->gerar($c);
        $this->assertSame([30, 30], $c->feriasPeriodos()->orderBy('aquisitivo_inicio')->pluck('dias_direito')->all(), 'só os novos usam o direito novo: 2 períodos, ambos de antes da mudança');
    }

    public function test_so_colaborador_ativo_com_admissao_tem_periodos(): void
    {
        $this->assertCount(0, $this->gerar(Colaborador::factory()->create(['status' => StatusColaborador::PreAdmissao, 'data_admissao' => null])));
        $this->assertCount(0, $this->gerar($this->ativo('2025-01-01', ['status' => StatusColaborador::Desligado])));
        $this->assertCount(0, $this->gerar(Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => null])));
        $this->assertCount(0, $this->gerar($this->ativo('2027-01-01')), 'admissão no futuro');
        $this->assertSame(0, FeriasPeriodo::count());
    }

    public function test_periodo_apagado_nao_volta_na_geracao(): void
    {
        $c = $this->ativo('2025-11-18');
        $this->gerar($c)->first()->delete();

        $this->assertCount(0, $this->gerar($c));
    }

    public function test_comando_diario_cria_o_que_falta_para_todos_os_ativos(): void
    {
        $a = $this->ativo('2025-11-18');
        $b = $this->ativo('2026-05-01');
        $desligado = $this->ativo('2025-01-01', ['status' => StatusColaborador::Desligado]);

        $this->artisan('ferias:periodos')->expectsOutputToContain('3 período(s) criado(s) para 2 colaborador(es)')->assertSuccessful();
        $this->assertSame([2, 1, 0], [$a->feriasPeriodos()->count(), $b->feriasPeriodos()->count(), $desligado->feriasPeriodos()->count()]);

        $this->artisan('ferias:periodos')->expectsOutputToContain('Nenhum período novo')->assertSuccessful();
    }

    public function test_concluir_a_admissao_abre_o_primeiro_periodo(): void
    {
        AdmissaoChecklistItem::factory()->create(['nome' => 'Ficha', 'obrigatorio' => false]);
        $c = Colaborador::factory()->create(['status' => StatusColaborador::PreAdmissao]);
        Remuneracao::factory()->create(['colaborador_id' => $c->id]);
        app(IniciarAdmissao::class)->executar($c);

        app(ConcluirAdmissao::class)->executar($c, Carbon::parse('2026-11-16'));

        $p = $c->feriasPeriodos()->sole();
        $this->assertSame(['2026-11-16', '2027-11-15'], [$p->aquisitivo_inicio->toDateString(), $p->aquisitivo_fim->toDateString()]);
    }

    public function test_baixar_e_devolver_saldo_nunca_deixam_negativo_nem_acima_do_direito(): void
    {
        $p = FeriasPeriodo::factory()->create(['dias_direito' => 30, 'dias_utilizados' => 0]);
        $ajustar = app(AjustarSaldoFerias::class);

        $this->assertSame(20, $ajustar->baixar($p, 10)->saldo());
        $this->assertSame(0, $ajustar->baixar($p, 20)->saldo());

        foreach ([fn () => $ajustar->baixar($p, 1), fn () => $ajustar->baixar($p, 0), fn () => $ajustar->baixar($p, -3), fn () => $ajustar->devolver($p, 31), fn () => $ajustar->devolver($p, 0)] as $caso) {
            try {
                $caso();
                $this->fail('Aceitou ajuste inválido');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(30, $p->fresh()->dias_utilizados);
        $this->assertSame(5, $ajustar->devolver($p, 5)->saldo());
        $this->assertSame(25, $p->fresh()->dias_utilizados);
        $this->assertSame(1, Activity::where('event', 'ferias-saldo-devolvido')->count());
        $this->assertSame(2, Activity::where('event', 'ferias-saldo-baixado')->count());
    }

    public function test_fechamento_do_alerta_classifica_pelo_limite_de_gozo(): void
    {
        $c = $this->ativo('2020-01-01');
        $mk = fn (string $limite, array $extra = []) => FeriasPeriodo::factory()->create($extra + ['colaborador_id' => $c->id, 'limite_gozo' => $limite, 'aquisitivo_inicio' => Carbon::parse($limite)->subYears(2)->toDateString()]);
        $vencido = $mk('2026-11-17');
        $hoje = $mk('2026-11-18');
        $ultimo = $mk('2027-01-17');
        $mk('2027-01-18');
        $mk('2026-11-01', ['dias_utilizados' => 30]);

        $r = app(FeriasAVencer::class)->consultar(60, now());

        $this->assertSame([$vencido->id], $r['vencidos']->pluck('id')->all());
        $this->assertSame([$hoje->id, $ultimo->id], $r['a_vencer']->pluck('id')->all());
    }

    public function test_alerta_ignora_colaborador_desligado_e_so_conta_quem_tem_saldo(): void
    {
        $desligado = $this->ativo('2020-01-01', ['status' => StatusColaborador::Desligado]);
        FeriasPeriodo::factory()->create(['colaborador_id' => $desligado->id, 'limite_gozo' => '2026-11-01']);

        $r = app(FeriasAVencer::class)->consultar(60, now());

        $this->assertCount(0, $r['vencidos']);
    }

    public function test_comando_envia_o_resumo_a_quem_tem_a_permissao_e_respeita_a_antecedencia_configurada(): void
    {
        Notification::fake();
        $destinatario = $this->usuarioCom(AlertasFerias::PERMISSAO);
        $semPermissao = $this->usuarioCom('ferias-list');
        $c = $this->ativo('2020-01-01');
        FeriasPeriodo::factory()->create(['colaborador_id' => $c->id, 'limite_gozo' => '2026-11-10']);
        FeriasPeriodo::factory()->create(['colaborador_id' => $c->id, 'aquisitivo_inicio' => '2023-01-01', 'limite_gozo' => '2026-12-18']);

        $this->artisan('alertas:ferias')->expectsOutputToContain('1 vencido(s), 1 a vencer')->assertSuccessful();
        Notification::assertSentTo($destinatario, ResumoFerias::class);
        Notification::assertNotSentTo($semPermissao, ResumoFerias::class);

        Configuracao::definir('alertas.ferias', ['dias' => 10]);
        $this->artisan('alertas:ferias')->expectsOutputToContain('1 vencido(s), 0 a vencer')->assertSuccessful();
    }

    public function test_resumo_mostra_colaborador_periodo_saldo_e_limite(): void
    {
        $c = $this->ativo('2020-01-01');
        $p = FeriasPeriodo::factory()->create(['colaborador_id' => $c->id, 'aquisitivo_inicio' => '2024-03-02', 'aquisitivo_fim' => '2025-03-01', 'dias_utilizados' => 12, 'limite_gozo' => '2026-03-01']);
        $p->load('colaborador.pessoaFisica');

        $texto = implode("\n", (new ResumoFerias(collect([$p]), collect(), 60, now()))->toMail(User::factory()->create())->introLines);

        $this->assertStringContainsString($c->pessoaFisica->nome, $texto);
        foreach (['02/03/2024', '01/03/2025', 'saldo 18 dia(s)', 'limite 01/03/2026'] as $trecho) {
            $this->assertStringContainsString($trecho, $texto);
        }
    }

    public function test_nada_a_avisar_nao_envia_e_sem_destinatario_avisa_o_admin(): void
    {
        Notification::fake();
        Log::spy();

        $this->artisan('alertas:ferias')->assertSuccessful();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, AlertasFerias::PERMISSAO))->once();
        $this->assertArrayHasKey(AlertasFerias::PERMISSAO, AvisosAdmin::alertasSemDestinatario());

        $this->usuarioCom(AlertasFerias::PERMISSAO);
        $this->artisan('alertas:ferias')->expectsOutputToContain('Nenhum saldo de férias')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_seeder_cria_o_fluxo_padrao_de_dois_niveis_uma_vez_so(): void
    {
        $this->seed([\Database\Seeders\PermissionSeeder::class, FeriasNivelSeeder::class]);

        $niveis = \App\Models\FeriasNivel::with('permissao')->orderBy('ordem')->get();
        $this->assertSame(['gestor_equipe', 'permissao'], $niveis->map(fn ($n) => $n->tipo_aprovador->value)->all());
        $this->assertSame('ferias-aprovar-rh', $niveis[1]->permissao->name);

        $niveis[1]->update(['ativo' => false]);
        $this->seed(FeriasNivelSeeder::class);
        $this->assertSame(2, \App\Models\FeriasNivel::count());
        $this->assertFalse($niveis[1]->fresh()->ativo, 'o que o admin mudou não volta');
    }

    public function test_ficha_do_colaborador_mostra_periodos_e_saldo_so_para_quem_ve_ferias(): void
    {
        $c = $this->ativo('2024-03-02');
        $this->gerar($c);
        $c->feriasPeriodos()->orderBy('aquisitivo_inicio')->first()->update(['dias_utilizados' => 30]);

        $this->actingAs($this->usuarioCom('colaborador-list', 'ferias-list'))
            ->get(route('colaborador.show', $c->id))->assertOk()->assertSee('Saldo total: ')->assertSee('60 dia(s)')->assertSee('02/03/2025 a 01/03/2026');

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('colaborador.show', $c->id))->assertOk()->assertDontSee('Saldo total');
    }
}

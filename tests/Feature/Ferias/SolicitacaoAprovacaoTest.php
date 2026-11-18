<?php

namespace Tests\Feature\Ferias;

use App\Actions\Ferias\CancelarFerias;
use App\Actions\Ferias\DecidirFerias;
use App\Actions\Ferias\SolicitarFerias;
use App\Enums\DecisaoFerias;
use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAprovadorFerias;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\Obra;
use App\Models\User;
use App\Notifications\FeriasDecidida;
use App\Notifications\FeriasSolicitada;
use App\Permission;
use App\Support\AvisosAdmin;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Ferias\AprovadoresFerias;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SolicitacaoAprovacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 09:00:00');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Fluxo padrão: 1. gestor da equipe; 2. quem tem ferias-aprovar-rh. */
    private function niveisPadrao(): void
    {
        FeriasNivel::create(['ordem' => 1, 'tipo_aprovador' => TipoAprovadorFerias::GestorEquipe, 'ativo' => true]);
        FeriasNivel::create(['ordem' => 2, 'tipo_aprovador' => TipoAprovadorFerias::Permissao, 'permission_id' => Permission::firstOrCreate(['name' => 'ferias-aprovar-rh'], ['display_name' => 'RH'])->id, 'ativo' => true]);
    }

    private function colaborador(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-03-02']);
    }

    private function periodo(Colaborador $colaborador, array $extra = []): FeriasPeriodo
    {
        return FeriasPeriodo::factory()->create($extra + ['colaborador_id' => $colaborador->id]);
    }

    private function equipeComGestor(Colaborador $colaborador, ?User $gestor = null): array
    {
        $equipe = Equipe::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $colaborador->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-03-02', 'fim' => null]);
        $gestor ??= User::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-03-02', 'fim' => null]);

        return [$equipe, $gestor];
    }

    private function rh(): User
    {
        return $this->usuarioCom('ferias-aprovar-rh', 'ferias-list');
    }

    private function solicitar(Colaborador $c, FeriasPeriodo $p, string $inicio = '2026-08-03', string $fim = '2026-08-12', ?User $solicitante = null): FeriasSolicitacao
    {
        return app(SolicitarFerias::class)->executar($c, $p, Carbon::parse($inicio), Carbon::parse($fim), $solicitante)['solicitacao'];
    }

    private function recusaCom(callable $acao, string $trecho): void
    {
        try {
            $acao();
            $this->fail('Não recusou');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($trecho, implode(' ', $e->validator->errors()->all()));
        }
    }

    // ---- Solicitação -------------------------------------------------------

    public function test_solicitacao_valida_nasce_pendente_no_nivel_1_e_avisa_o_gestor(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $gestor] = $this->equipeComGestor($colaborador);
        $periodo = $this->periodo($colaborador);

        $s = $this->solicitar($colaborador, $periodo);

        $this->assertSame(StatusSolicitacaoFerias::Pendente, $s->status);
        $this->assertSame(1, $s->nivel_atual);
        $this->assertSame(10, $s->dias);
        $this->assertSame(0, $periodo->fresh()->dias_utilizados, 'O saldo só baixa na aprovação');
        Notification::assertSentTo($gestor, FeriasSolicitada::class);
    }

    public function test_recusa_saldo_insuficiente_considerando_as_pendentes(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $periodo = $this->periodo($colaborador, ['dias_utilizados' => 5]);
        $this->solicitar($colaborador, $periodo, '2026-08-03', '2026-08-17'); // 15 dias de 25

        $this->recusaCom(fn () => $this->solicitar($colaborador, $periodo, '2026-09-01', '2026-09-11'), '10 dia(s) livre');
    }

    public function test_minimo_de_dias_com_excecao_para_o_saldo_que_sobrou(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $periodo = $this->periodo($colaborador, ['dias_utilizados' => 26]);

        $this->recusaCom(fn () => $this->solicitar($colaborador, $periodo, '2026-08-03', '2026-08-04'), 'pelo menos 5 dias');
        $this->assertSame(4, $this->solicitar($colaborador, $periodo, '2026-08-03', '2026-08-06')->dias, 'Pedir exatamente o saldo que sobrou dispensa o mínimo');
    }

    public function test_recusa_sobreposicao_inicio_antes_do_aquisitivo_desligamento_e_status(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $periodo = $this->periodo($colaborador);
        $this->solicitar($colaborador, $periodo, '2026-08-03', '2026-08-12');

        $this->recusaCom(fn () => $this->solicitar($colaborador, $periodo, '2026-08-12', '2026-08-20'), 'se sobrepõe');
        $this->recusaCom(fn () => $this->solicitar($colaborador, $periodo, '2026-02-01', '2026-02-10'), 'período aquisitivo');
        $this->recusaCom(fn () => $this->solicitar($colaborador, $periodo, '2026-09-10', '2026-09-05'), 'antes do início');

        $desligando = $this->colaborador(['data_desligamento' => '2026-09-05']);
        $this->recusaCom(fn () => $this->solicitar($desligando, $this->periodo($desligando), '2026-09-01', '2026-09-10'), 'desligamento');

        $preAdmissao = Colaborador::factory()->create(['status' => StatusColaborador::PreAdmissao]);
        $this->recusaCom(fn () => $this->solicitar($preAdmissao, $this->periodo($preAdmissao)), 'colaborador ativo');

        $outro = $this->colaborador();
        $this->recusaCom(fn () => $this->solicitar($outro, $periodo), 'não é deste colaborador');
    }

    public function test_competencia_fechada_recusa_a_solicitacao(): void
    {
        $this->niveisPadrao();
        $this->app->instance(CompetenciaFechada::class, new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === '2026-08';
            }
        });
        $colaborador = $this->colaborador();

        $this->recusaCom(fn () => $this->solicitar($colaborador, $this->periodo($colaborador), '2026-07-28', '2026-08-05'), 'competência de 08/2026');
    }

    public function test_avisa_conflito_com_colega_da_equipe_ou_da_obra_sem_bloquear(): void
    {
        $this->niveisPadrao();
        $obra = Obra::factory()->create();
        $a = $this->colaborador(['obra_id' => $obra->id]);
        $b = $this->colaborador(['obra_id' => $obra->id]);
        $this->solicitar($a, $this->periodo($a), '2026-08-05', '2026-08-14');

        $resultado = app(SolicitarFerias::class)->executar($b, $this->periodo($b), Carbon::parse('2026-08-10'), Carbon::parse('2026-08-19'), null);

        $this->assertStringContainsString($a->pessoaFisica->nome, $resultado['avisos'][0]);
        $this->assertSame(StatusSolicitacaoFerias::Pendente, $resultado['solicitacao']->status);
    }

    // ---- Fluxo -------------------------------------------------------------

    public function test_dois_niveis_aprovam_baixam_o_saldo_e_avisam_o_solicitante(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $gestor] = $this->equipeComGestor($colaborador);
        $rh = $this->rh();
        $solicitante = User::factory()->create();
        $periodo = $this->periodo($colaborador);
        $s = $this->solicitar($colaborador, $periodo, solicitante: $solicitante);
        $decidir = app(DecidirFerias::class);

        $decidir->executar($s, $gestor, DecisaoFerias::Aprovada);

        $this->assertSame(StatusSolicitacaoFerias::Pendente, $s->fresh()->status);
        $this->assertSame(2, $s->fresh()->nivel_atual);
        Notification::assertSentTo($rh, FeriasSolicitada::class);
        $this->assertSame(0, $periodo->fresh()->dias_utilizados);

        $decidir->executar($s, $rh, DecisaoFerias::Aprovada, 'Boas férias');

        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $s->fresh()->status);
        $this->assertSame(10, $periodo->fresh()->dias_utilizados);
        Notification::assertSentTo($solicitante, FeriasDecidida::class);
        $this->assertSame([1, 2], $s->aprovacoes()->orderBy('nivel')->pluck('nivel')->all());
    }

    public function test_gestor_de_outra_equipe_e_o_solicitante_nao_decidem(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $this->equipeComGestor($colaborador);
        [, $forasteiro] = $this->equipeComGestor($this->colaborador());
        $solicitante = $this->rh();
        $s = $this->solicitar($colaborador, $this->periodo($colaborador), solicitante: $solicitante);

        $this->recusaCom(fn () => app(DecidirFerias::class)->executar($s, $forasteiro, DecisaoFerias::Aprovada), 'não está entre os aprovadores');
        $this->recusaCom(fn () => app(DecidirFerias::class)->executar($s, $solicitante, DecisaoFerias::Aprovada), 'não está entre os aprovadores');
    }

    public function test_recusa_exige_motivo_encerra_e_nao_baixa_saldo(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $gestor] = $this->equipeComGestor($colaborador);
        $periodo = $this->periodo($colaborador);
        $solicitante = User::factory()->create();
        $s = $this->solicitar($colaborador, $periodo, solicitante: $solicitante);

        $this->recusaCom(fn () => app(DecidirFerias::class)->executar($s, $gestor, DecisaoFerias::Recusada), 'motivo da recusa');
        app(DecidirFerias::class)->executar($s, $gestor, DecisaoFerias::Recusada, 'Obra em fase crítica');

        $this->assertSame(StatusSolicitacaoFerias::Recusada, $s->fresh()->status);
        $this->assertSame(0, $periodo->fresh()->dias_utilizados);
        Notification::assertSentTo($solicitante, FeriasDecidida::class);
        $this->recusaCom(fn () => app(DecidirFerias::class)->executar($s, $gestor, DecisaoFerias::Aprovada), 'já foi decidida');
    }

    public function test_aprovacao_final_recusada_se_o_saldo_acabou_no_meio(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $gestor] = $this->equipeComGestor($colaborador);
        $rh = $this->rh();
        $periodo = $this->periodo($colaborador);
        $s = $this->solicitar($colaborador, $periodo);
        app(DecidirFerias::class)->executar($s, $gestor, DecisaoFerias::Aprovada);
        $periodo->update(['dias_utilizados' => 25]); // ajuste manual do RH no meio do caminho

        $this->recusaCom(fn () => app(DecidirFerias::class)->executar($s, $rh, DecisaoFerias::Aprovada), 'saldo do período');
        $this->assertSame(StatusSolicitacaoFerias::Pendente, $s->fresh()->status);
    }

    // ---- AprovadoresFerias -------------------------------------------------

    public function test_colaborador_em_duas_equipes_tem_os_gestores_das_duas(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $g1] = $this->equipeComGestor($colaborador);
        [, $g2] = $this->equipeComGestor($colaborador);
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));

        $fila = app(AprovadoresFerias::class)->resolver($s, now());

        $this->assertEqualsCanonicalizing([$g1->id, $g2->id], $fila['usuarios']->pluck('id')->all());
    }

    public function test_sem_equipe_o_nivel_1_e_pulado_e_registrado_como_escalada(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $rh = $this->rh();
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));

        $this->assertSame(2, $s->fresh()->nivel_atual);
        $escalada = $s->aprovacoes()->firstOrFail();
        $this->assertTrue($escalada->escalada);
        $this->assertSame(DecisaoFerias::Pulada, $escalada->decisao);
        Notification::assertSentTo($rh, FeriasSolicitada::class);

        app(DecidirFerias::class)->executar($s, $rh, DecisaoFerias::Aprovada);
        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $s->fresh()->status, 'O nível 2 era o último');
    }

    public function test_gestor_pedindo_as_proprias_ferias_vai_a_outro_gestor_ou_ao_rh(): void
    {
        $this->niveisPadrao();
        $rh = $this->rh();
        $colaborador = $this->colaborador();
        $euGestor = User::factory()->create(['pessoa_fisica_id' => $colaborador->pessoa_fisica_id]);

        // Sozinho na equipe: o nível 1 fica vazio e a solicitação vai ao RH.
        [$equipe] = $this->equipeComGestor($colaborador, $euGestor);
        $s = $this->solicitar($colaborador, $this->periodo($colaborador), solicitante: $euGestor);
        $this->assertSame([$rh->id], app(AprovadoresFerias::class)->resolver($s, now())['usuarios']->pluck('id')->all());
        $this->assertSame(2, $s->fresh()->nivel_atual);

        // Com outro gestor na equipe, ele decide no nível 1.
        $outro = User::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $outro->id, 'inicio' => '2025-03-02', 'fim' => null]);
        $s->update(['nivel_atual' => 1]);
        $fila = app(AprovadoresFerias::class)->resolver($s->fresh(), now());
        $this->assertSame([$outro->id], $fila['usuarios']->pluck('id')->all());
        $this->assertSame(1, $fila['nivel']);
    }

    public function test_rh_pedindo_as_proprias_ferias_no_ultimo_nivel_vai_a_quem_aprova_qualquer(): void
    {
        $this->niveisPadrao();
        $rh = $this->rh();
        $colaborador = $this->colaborador();
        $rh->update(['pessoa_fisica_id' => $colaborador->pessoa_fisica_id]);
        $chefe = $this->usuarioCom('ferias-aprovar-qualquer');
        $s = $this->solicitar($colaborador, $this->periodo($colaborador), solicitante: $rh);

        $fila = app(AprovadoresFerias::class)->resolver($s, now());

        $this->assertTrue($fila['fallback']);
        $this->assertSame([$chefe->id], $fila['usuarios']->pluck('id')->all());
        app(DecidirFerias::class)->executar($s, $chefe, DecisaoFerias::Aprovada);
        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $s->fresh()->status);
        $this->assertTrue($s->aprovacoes()->where('decisao', 'aprovada')->firstOrFail()->escalada);
    }

    public function test_ninguem_elegivel_deixa_aviso_no_painel_do_admin(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));

        $this->assertSame(['ferias-aprovar-qualquer'], array_keys(AvisosAdmin::alertasSemDestinatario()));
        $this->assertTrue($s->fresh()->status === StatusSolicitacaoFerias::Pendente);

        $this->usuarioCom('ferias-aprovar-qualquer');
        app(\App\Actions\Ferias\SincronizarFilaFerias::class)->executar($s->fresh());
        $this->assertSame([], AvisosAdmin::alertasSemDestinatario());
    }

    public function test_troca_de_equipe_com_pendente_muda_a_fila(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [$equipeA, $gestorA] = $this->equipeComGestor($colaborador);
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));
        $this->assertSame([$gestorA->id], app(AprovadoresFerias::class)->resolver($s, now())['usuarios']->pluck('id')->all());

        ColaboradorEquipe::where('colaborador_id', $colaborador->id)->update(['fim' => '2026-06-30']);
        [, $gestorB] = $this->equipeComGestor($colaborador);
        ColaboradorEquipe::where('equipe_id', '!=', $equipeA->id)->where('colaborador_id', $colaborador->id)->update(['inicio' => '2026-07-01']);

        $this->assertSame([$gestorB->id], app(AprovadoresFerias::class)->resolver($s->fresh(), now())['usuarios']->pluck('id')->all());
    }

    public function test_resolver_em_lote_da_o_mesmo_resultado_que_um_a_um(): void
    {
        $this->niveisPadrao();
        $rh = $this->rh();
        $solicitacoes = collect();
        foreach ([true, false, true] as $temEquipe) {
            $c = $this->colaborador();
            if ($temEquipe) {
                $this->equipeComGestor($c);
            }
            $solicitacoes->push($this->solicitar($c, $this->periodo($c), solicitante: User::factory()->create())->load('colaborador'));
        }
        $servico = app(AprovadoresFerias::class);
        $lote = $servico->carregarLote($solicitacoes, now());

        foreach ($solicitacoes as $s) {
            $sozinha = $servico->resolver($s->fresh('colaborador'), now());
            $emLote = $servico->resolver($s, now(), $lote);
            $this->assertSame($sozinha['nivel'], $emLote['nivel']);
            $this->assertSame($sozinha['usuarios']->pluck('id')->all(), $emLote['usuarios']->pluck('id')->all());
        }
        $this->assertContains($rh->id, $servico->resolver($solicitacoes[1], now(), $lote)['usuarios']->pluck('id')->all(), 'Sem equipe, o nível 1 é pulado e o RH decide');
    }

    public function test_lembrete_diario_chega_a_quem_e_aprovador_hoje_inclusive_o_novo_gestor(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [$equipe, $antigo] = $this->equipeComGestor($colaborador);
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));
        Notification::fake();

        $novo = User::factory()->create();
        EquipeGestor::where('equipe_id', $equipe->id)->update(['fim' => '2026-06-30']);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $novo->id, 'inicio' => '2026-07-01', 'fim' => null]);

        $this->artisan('ferias:pendentes')->assertSuccessful();

        Notification::assertSentTo($novo, \App\Notifications\ResumoFeriasPendentes::class);
        Notification::assertNotSentTo($antigo, \App\Notifications\ResumoFeriasPendentes::class);
        $this->assertSame(StatusSolicitacaoFerias::Pendente, $s->fresh()->status);
    }

    public function test_gestor_com_vinculo_encerrado_deixa_de_ser_elegivel(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [$equipe, $gestor] = $this->equipeComGestor($colaborador);
        $rh = $this->rh();
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));
        EquipeGestor::where('equipe_id', $equipe->id)->update(['fim' => '2026-06-30']);

        $fila = app(AprovadoresFerias::class)->resolver($s->fresh(), now());

        $this->assertSame([$rh->id], $fila['usuarios']->pluck('id')->all());
        $this->assertFalse(app(AprovadoresFerias::class)->podeDecidir($s->fresh(), $gestor, now()));
    }

    // ---- Cancelamento ------------------------------------------------------

    public function test_cancelar_aprovada_devolve_o_saldo_e_pendente_nao_mexe(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $rh = $this->rh();
        $periodo = $this->periodo($colaborador);
        $s = $this->solicitar($colaborador, $periodo);
        app(DecidirFerias::class)->executar($s, $rh, DecisaoFerias::Aprovada);
        $this->assertSame(10, $periodo->fresh()->dias_utilizados);

        app(CancelarFerias::class)->executar($s);
        $this->assertSame(0, $periodo->fresh()->dias_utilizados);
        $this->assertSame(StatusSolicitacaoFerias::Cancelada, $s->fresh()->status);

        $pendente = $this->solicitar($colaborador, $periodo, '2026-10-05', '2026-10-14');
        app(CancelarFerias::class)->executar($pendente);
        $this->assertSame(0, $periodo->fresh()->dias_utilizados);
        $this->recusaCom(fn () => app(CancelarFerias::class)->executar($pendente), 'pendentes ou aprovadas');
    }

    public function test_cancelar_aprovada_em_competencia_fechada_e_recusado(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $rh = $this->rh();
        $periodo = $this->periodo($colaborador);
        $s = $this->solicitar($colaborador, $periodo);
        app(DecidirFerias::class)->executar($s, $rh, DecisaoFerias::Aprovada);
        $this->app->instance(CompetenciaFechada::class, new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === '2026-08';
            }
        });

        $this->recusaCom(fn () => app(CancelarFerias::class)->executar($s), 'competência de 08/2026');
        $this->assertSame(10, $periodo->fresh()->dias_utilizados);
    }

    // ---- Telas -------------------------------------------------------------

    public function test_gestor_so_solicita_para_quem_gere_e_pela_tela(): void
    {
        $this->niveisPadrao();
        $gestor = $this->usuarioCom('ferias-solicitar', 'ferias-list', 'equipe-gerir', 'colaborador-list');
        $meu = $this->colaborador();
        $this->equipeComGestor($meu, $gestor);
        $alheio = $this->colaborador();
        $dados = fn (Colaborador $c) => ['periodo_id' => $this->periodo($c)->id, 'inicio' => '2026-08-03', 'fim' => '2026-08-12'];

        $this->actingAs($gestor)
            ->postJson(route('ferias.solicitar', $alheio->id), $dados($alheio))->assertNotFound();
        $this->postJson(route('ferias.solicitar', $meu->id), $dados($meu))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, FeriasSolicitacao::count());
    }

    public function test_tela_lista_o_que_espera_a_decisao_e_o_aprovador_decide_pela_tela(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        [, $gestor] = $this->equipeComGestor($colaborador);
        $gestor = $this->comPermissoes($gestor, 'ferias-list');
        $s = $this->solicitar($colaborador, $this->periodo($colaborador));
        $outro = $this->usuarioCom('ferias-list');

        $this->actingAs($gestor)->get(route('ferias.index'))->assertOk()->assertSee($colaborador->pessoaFisica->nome)->assertSee('Decidir');
        $this->actingAs($outro)->get(route('ferias.index'))->assertOk()->assertDontSee($colaborador->pessoaFisica->nome);
        $this->actingAs($outro)->get(route('ferias.decidir.form', $s->id))->assertForbidden();

        $this->actingAs($gestor)->get(route('ferias.decidir.form', $s->id))->assertOk();
        $this->postJson(route('ferias.decidir', $s->id), ['decisao' => 'recusada'])->assertStatus(422);
        $this->postJson(route('ferias.decidir', $s->id), ['decisao' => 'recusada', 'observacao' => 'Sem cobertura'])->assertOk();
        $this->assertSame(StatusSolicitacaoFerias::Recusada, $s->fresh()->status);
    }

    public function test_ficha_mostra_solicitacoes_e_formulario_so_para_quem_solicita(): void
    {
        $this->niveisPadrao();
        $colaborador = $this->colaborador();
        $periodo = $this->periodo($colaborador);
        $this->solicitar($colaborador, $periodo);

        $this->actingAs($this->usuarioCom('colaborador-list', 'ferias-list'))->get(route('colaborador.show', $colaborador->id))
            ->assertOk()->assertSee('03/08/2026 a 12/08/2026')->assertDontSee('Solicitar</button>', false);
        $this->actingAs($this->usuarioCom('colaborador-list', 'ferias-list', 'ferias-solicitar'))->get(route('colaborador.show', $colaborador->id))
            ->assertOk()->assertSee('Solicitar</button>', false);
    }

    public function test_configuracao_de_niveis_mantem_sempre_um_ativo(): void
    {
        $this->niveisPadrao();
        $this->actingAs($this->usuarioCom('ferias-niveis-configurar'));
        $n1 = FeriasNivel::where('ordem', 1)->firstOrFail();
        $n2 = FeriasNivel::where('ordem', 2)->firstOrFail();

        $this->get(route('ferias-nivel.index'))->assertOk();
        $this->postJson(route('ferias-nivel.store'), ['ordem' => 3, 'tipo_aprovador' => 'permissao'])->assertStatus(422);
        $this->postJson(route('ferias-nivel.store'), ['ordem' => 2, 'tipo_aprovador' => 'gestor_equipe'])->assertStatus(422);
        $this->postJson(route('ferias-nivel.store'), ['ordem' => 3, 'tipo_aprovador' => 'permissao', 'permission_id' => $n2->permission_id])->assertOk();

        $this->deleteJson(route('ferias-nivel.delete', FeriasNivel::where('ordem', 3)->firstOrFail()->id))->assertStatus(201);
        $this->deleteJson(route('ferias-nivel.delete', $n1->id))->assertStatus(201);
        $this->deleteJson(route('ferias-nivel.delete', $n2->id))->assertStatus(422);
        $this->postJson(route('ferias-nivel.store', $n2->id), ['ordem' => 2, 'tipo_aprovador' => 'permissao', 'permission_id' => $n2->permission_id, 'ativo' => 0])->assertStatus(422);
        $this->assertTrue($n2->fresh()->ativo);
    }

    public function test_sem_permissao_as_rotas_de_ferias_dao_403(): void
    {
        $this->actingAs($this->usuarioCom('colaborador-list'));

        $this->get(route('ferias.index'))->assertForbidden();
        $this->get(route('ferias-nivel.index'))->assertForbidden();
        $this->postJson(route('ferias.solicitar', 1), [])->assertForbidden();
        $this->postJson(route('ferias.cancelar', 1))->assertForbidden();
    }

    private function comPermissoes(User $usuario, string ...$permissoes): User
    {
        $perfil = $usuario->roles()->firstOrNew();
        if (! $perfil->exists) {
            $perfil = \App\Role::create(['name' => 'perfil-'.\Illuminate\Support\Str::random(8), 'display_name' => 'Teste']);
            $usuario->roles()->attach($perfil);
        }
        foreach ($permissoes as $p) {
            $perfil->permissions()->attach(Permission::firstOrCreate(['name' => $p], ['display_name' => $p])->id);
        }
        $usuario->esquecerPermissoes();

        return $usuario;
    }
}

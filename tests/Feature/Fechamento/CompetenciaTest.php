<?php

namespace Tests\Feature\Fechamento;

use App\Actions\Fechamento\AbrirCompetencia;
use App\Actions\Fechamento\AprovarCompetencia;
use App\Actions\Fechamento\CalcularFechamentoColaborador;
use App\Actions\Fechamento\LancarAjusteAvulso;
use App\Actions\Fechamento\ReabrirCompetencia;
use App\Enums\StatusColaborador;
use App\Enums\StatusCompetencia;
use App\Enums\StatusFechamento;
use App\Enums\TipoAjuste;
use App\Enums\TipoCalculoBeneficio;
use App\Models\AjusteAvulso;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Models\Ocorrencia;
use App\Models\Remuneracao;
use App\Models\User;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CompetenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Colaborador ativo, com R$ 3.000,00, vale transporte (R$ 300,00 fixo) e VA (R$ 30,00 por dia). */
    private function colaborador(string $admissao = '2025-01-01', ?string $desligamento = null): Colaborador
    {
        $c = Colaborador::factory()->create(['status' => $desligamento ? StatusColaborador::Desligado : StatusColaborador::Ativo, 'data_admissao' => $admissao, 'data_desligamento' => $desligamento]);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3000, 'vigencia_inicio' => $admissao]);
        $vt = Beneficio::firstOrCreate(['nome' => 'Vale transporte'], ['tipo_calculo' => TipoCalculoBeneficio::FixoMensal]);
        if (! BeneficioValor::where('beneficio_id', $vt->id)->exists()) {
            BeneficioValor::factory()->create(['beneficio_id' => $vt->id, 'valor' => 300, 'vigencia_inicio' => '2020-01-01']);
        }
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $vt->id, 'inicio' => $admissao]);

        return $c;
    }

    private function abrir(string $anoMes = '2026-09'): Competencia
    {
        return app(AbrirCompetencia::class)->executar($anoMes);
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

    public function test_abrir_calcula_quem_esteve_ativo_no_mes_e_vai_para_conferencia(): void
    {
        $ativo = $this->colaborador();
        $admitido = $this->colaborador('2026-09-15');
        $desligado = $this->colaborador('2025-01-01', '2026-09-10');
        $jaSaiu = $this->colaborador('2025-01-01', '2026-08-31');
        $entraDepois = $this->colaborador('2026-10-01');
        Colaborador::factory()->create(); // pré-admissão sem data

        $competencia = $this->abrir();

        $this->assertSame(StatusCompetencia::EmConferencia, $competencia->fresh()->status);
        $this->assertEqualsCanonicalizing([$ativo->id, $admitido->id, $desligado->id], $competencia->fechamentos()->pluck('colaborador_id')->all());
        $f = Fechamento::where('colaborador_id', $ativo->id)->firstOrFail();
        $this->assertSame('3300.00', $f->total_liquido);
        $this->assertSame(StatusFechamento::Calculado, $f->status);
        $this->assertTrue(Activity::where('event', 'competencia-aberta')->exists());
        $this->assertTrue(Activity::where('event', 'competencia-calculada')->exists());
    }

    public function test_regras_para_abrir(): void
    {
        $this->abrir('2026-09');

        $this->recusaCom(fn () => $this->abrir('2026-09'), 'já foi aberta');
        $this->recusaCom(fn () => $this->abrir('2026-11'), 'ainda não começou');
        $this->recusaCom(fn () => $this->abrir('09/2026'), 'AAAA-MM');
        $this->assertSame(StatusCompetencia::EmConferencia, $this->abrir('2026-10')->fresh()->status, 'O mês corrente pode ser aberto');
    }

    public function test_ajuste_avulso_recalcula_o_colaborador_e_pode_ser_removido(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        $lancar = app(LancarAjusteAvulso::class);
        $usuario = User::factory()->create();

        $credito = $lancar->executar($competencia, $c, TipoAjuste::Credito, '50.00', 'Bônus de obra', $usuario->id);
        $lancar->executar($competencia, $c, TipoAjuste::Debito, '20,00', 'Adiantamento', $usuario->id);

        $f = Fechamento::where('colaborador_id', $c->id)->firstOrFail();
        $this->assertSame(['30.00', '3330.00'], [$f->total_ajustes, $f->total_liquido]);
        $this->assertSame(4, $f->itens()->count(), 'Remuneração, benefício e os dois ajustes');

        $lancar->remover($credito);
        $f->refresh();
        $this->assertSame(['-20.00', '3280.00'], [$f->total_ajustes, $f->total_liquido]);
        $this->assertTrue(Activity::where('event', 'ajuste-avulso-removido')->exists());
    }

    public function test_ajuste_exige_valor_justificativa_e_fechamento_do_colaborador(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        $lancar = app(LancarAjusteAvulso::class);

        $this->recusaCom(fn () => $lancar->executar($competencia, $c, TipoAjuste::Credito, '0', 'x'), 'maior que zero');
        $this->recusaCom(fn () => $lancar->executar($competencia, $c, TipoAjuste::Credito, '10', '  '), 'justificativa');
        $this->recusaCom(fn () => $lancar->executar($competencia, Colaborador::factory()->create(), TipoAjuste::Credito, '10', 'x'), 'não tem fechamento');
        $this->assertSame(0, AjusteAvulso::count());
    }

    public function test_recalcular_um_pega_mudancas_feitas_depois_do_calculo(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        Remuneracao::where('colaborador_id', $c->id)->update(['valor_mensal' => 4000]);

        $this->assertSame('3300.00', Fechamento::where('colaborador_id', $c->id)->firstOrFail()->total_liquido);
        app(CalcularFechamentoColaborador::class)->executar($c, $competencia);
        $this->assertSame('4300.00', Fechamento::where('colaborador_id', $c->id)->firstOrFail()->total_liquido);
        $this->assertSame(2, Fechamento::where('colaborador_id', $c->id)->firstOrFail()->itens()->count(), 'Recalcular substitui os itens');
    }

    public function test_aprovar_grava_snapshot_aguarda_nota_e_torna_imutavel(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        $aprovador = User::factory()->create();

        app(AprovarCompetencia::class)->executar($competencia, $aprovador);

        $competencia->refresh();
        $this->assertSame([StatusCompetencia::Aprovada, $aprovador->id], [$competencia->status, $competencia->aprovada_por]);
        $f = Fechamento::where('colaborador_id', $c->id)->firstOrFail();
        $this->assertSame(StatusFechamento::AguardandoNota, $f->status);
        $this->assertSame('3300.00', $f->snapshot['total_liquido']);
        $this->assertCount(2, $f->snapshot['itens']);
        $this->assertSame('corridos', $f->snapshot['regras']['base_proporcional']);
        $this->assertTrue(Activity::where('event', 'competencia-aprovada')->exists());

        $this->recusaCom(fn () => app(AprovarCompetencia::class)->executar($competencia, $aprovador), 'em conferência');
        $this->recusaCom(fn () => app(CalcularFechamentoColaborador::class)->executar($c, $competencia), 'já foi aprovada');
        $this->recusaCom(fn () => app(LancarAjusteAvulso::class)->executar($competencia, $c, TipoAjuste::Credito, '10', 'x'), 'já foi aprovada');
        $this->assertSame('3300.00', $f->fresh()->total_liquido);
    }

    public function test_aprovada_recusa_lancamentos_com_data_no_mes(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        app(AprovarCompetencia::class)->executar($competencia, User::factory()->create());
        $this->actingAs($this->usuarioCom('ocorrencia-gerir', 'feriado-gerir'));

        $this->assertTrue(app(CompetenciaFechada::class)->fechada(Carbon::parse('2026-09-30')));
        $this->assertFalse(app(CompetenciaFechada::class)->fechada(Carbon::parse('2026-10-01')));
        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $c->id, 'tipo' => 'falta', 'inicio' => '2026-09-10', 'fim' => '2026-09-10'])->assertStatus(422);
        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $c->id, 'tipo' => 'falta', 'inicio' => '2026-10-02', 'fim' => '2026-10-02'])->assertOk();
        $this->postJson(route('feriado.store'), ['data' => '2026-09-20', 'descricao' => 'X', 'abrangencia' => 'nacional'])->assertStatus(422);
        $this->assertSame(1, Ocorrencia::count());
    }

    public function test_avisos_de_calculo_travam_a_aprovacao_ate_confirmar(): void
    {
        $this->colaborador();
        $semRemuneracao = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
        $competencia = $this->abrir();
        $aprovador = User::factory()->create();

        $this->recusaCom(fn () => app(AprovarCompetencia::class)->executar($competencia, $aprovador), 'aviso de cálculo');
        $this->assertSame(StatusCompetencia::EmConferencia, $competencia->fresh()->status);

        app(AprovarCompetencia::class)->executar($competencia, $aprovador, ignorarAvisos: true);
        $this->assertSame(StatusCompetencia::Aprovada, $competencia->fresh()->status);
        $this->assertNotEmpty(Fechamento::where('colaborador_id', $semRemuneracao->id)->firstOrFail()->snapshot['avisos']);
    }

    public function test_competencia_sem_fechamento_nao_se_aprova(): void
    {
        $competencia = $this->abrir();

        $this->recusaCom(fn () => app(AprovarCompetencia::class)->executar($competencia, User::factory()->create()), 'nenhum fechamento');
    }

    public function test_reabrir_exige_justificativa_e_so_vale_antes_da_etapa_de_notas(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        $this->recusaCom(fn () => app(ReabrirCompetencia::class)->executar($competencia, 'x'), 'Só uma competência aprovada');
        app(AprovarCompetencia::class)->executar($competencia, User::factory()->create());

        $this->recusaCom(fn () => app(ReabrirCompetencia::class)->executar($competencia, '  '), 'Explique');

        Fechamento::where('colaborador_id', $c->id)->update(['status' => 'titulo_enviado']);
        $this->recusaCom(fn () => app(ReabrirCompetencia::class)->executar($competencia, 'Erro no VA'), 'já seguiram');
        Fechamento::where('colaborador_id', $c->id)->update(['status' => StatusFechamento::AguardandoNota->value]);

        app(ReabrirCompetencia::class)->executar($competencia, 'Erro no VA');

        $competencia->refresh();
        $this->assertSame([StatusCompetencia::EmConferencia, null], [$competencia->status, $competencia->aprovada_por]);
        $f = Fechamento::where('colaborador_id', $c->id)->firstOrFail();
        $this->assertSame(StatusFechamento::Calculado, $f->status);
        $this->assertArrayNotHasKey('itens', $f->snapshot);
        $this->assertSame('Erro no VA', Activity::where('event', 'competencia-reaberta')->firstOrFail()->properties['justificativa']);
        app(CalcularFechamentoColaborador::class)->executar($c, $competencia); // volta a recalcular
    }

    public function test_recalcular_todos_remove_quem_deixou_de_ter_periodo_no_mes(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        $this->assertSame(1, $competencia->fechamentos()->count());

        Colaborador::whereKey($c->id)->update(['data_admissao' => '2026-10-01']);
        \App\Jobs\CalcularCompetencia::dispatchSync($competencia->id);

        $this->assertSame(0, $competencia->fechamentos()->count());
    }

    public function test_recalcular_todos_apaga_o_fechamento_mesmo_sem_itens(): void
    {
        $c = $this->colaborador();
        $competencia = $this->abrir();
        Fechamento::where('colaborador_id', $c->id)->firstOrFail()->itens()->delete();
        Colaborador::whereKey($c->id)->update(['data_admissao' => '2026-10-01']);

        \App\Jobs\CalcularCompetencia::dispatchSync($competencia->id);

        $this->assertSame(0, $competencia->fechamentos()->count(), 'Fechamento sem itens também é removido');
    }

    // ---- Telas -------------------------------------------------------------

    public function test_fluxo_pelas_telas_com_permissoes_separadas(): void
    {
        $this->colaborador();
        $rh = $this->usuarioCom('fechamento-list', 'fechamento-conferir');
        $financeiro = $this->usuarioCom('fechamento-list', 'fechamento-aprovar');

        $this->actingAs($rh)->get(route('fechamento.index'))->assertOk();
        $id = $this->postJson(route('fechamento.abrir'), ['ano_mes' => '2026-09'])->assertOk()->json('data.id');
        $this->get(route('fechamento.show', $id))->assertOk()->assertSee('R$ 3.300,00')->assertDontSee('Aprovar competência');
        $fechamento = Fechamento::firstOrFail();
        $this->get(route('fechamento.detalhe', $fechamento->id))->assertOk()->assertSee('Remuneração mensal');
        $this->postJson(route('fechamento.ajuste.store', $fechamento->id), ['tipo' => 'credito', 'valor' => '100', 'justificativa' => 'Bônus'])->assertOk();
        $this->postJson(route('fechamento.recalcular', $fechamento->id))->assertOk();
        $this->postJson(route('fechamento.recalcular-todos', $id))->assertOk();
        $this->postJson(route('fechamento.aprovar', $id))->assertForbidden();

        $this->actingAs($financeiro)->get(route('fechamento.show', $id))->assertOk()->assertSee('Aprovar competência');
        $this->postJson(route('fechamento.aprovar', $id))->assertOk();
        $this->get(route('fechamento.show', $id))->assertOk()->assertSee('Aprovada')->assertSee('R$ 3.400,00');
        $this->postJson(route('fechamento.ajuste.store', $fechamento->id), ['tipo' => 'credito', 'valor' => '1', 'justificativa' => 'x'])->assertForbidden();

        $this->actingAs($rh)->postJson(route('fechamento.ajuste.store', $fechamento->id), ['tipo' => 'credito', 'valor' => '1', 'justificativa' => 'x'])->assertStatus(422);
        $this->postJson(route('fechamento.recalcular', $fechamento->id))->assertStatus(422);
        $this->postJson(route('fechamento.reabrir', $id), ['justificativa' => 'x'])->assertForbidden();
    }

    public function test_reabrir_pela_tela_e_so_do_admin(): void
    {
        $this->colaborador();
        $competencia = $this->abrir();
        app(AprovarCompetencia::class)->executar($competencia, User::factory()->create());

        $this->actingAs($this->usuarioCom('fechamento-list', 'fechamento-aprovar', 'fechamento-conferir'))
            ->postJson(route('fechamento.reabrir', $competencia->id), ['justificativa' => 'Erro'])->assertForbidden();
        $this->actingAs($this->usuarioCom('fechamento-reabrir'))
            ->postJson(route('fechamento.reabrir', $competencia->id), [])->assertStatus(422);
        $this->postJson(route('fechamento.reabrir', $competencia->id), ['justificativa' => 'Erro no VA'])->assertOk();
        $this->assertSame(StatusCompetencia::EmConferencia, $competencia->fresh()->status);
    }

    public function test_sem_permissao_o_fechamento_da_403(): void
    {
        $this->actingAs($this->usuarioCom('colaborador-list'));

        $this->get(route('fechamento.index'))->assertForbidden();
        $this->postJson(route('fechamento.abrir'), ['ano_mes' => '2026-09'])->assertForbidden();
    }
}

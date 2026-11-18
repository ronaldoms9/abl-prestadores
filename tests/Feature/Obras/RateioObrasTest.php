<?php

namespace Tests\Feature\Obras;

use App\Actions\Cadastros\DefinirRateioObras;
use App\Actions\Cadastros\SalvarColaborador;
use App\Actions\Fechamento\AbrirCompetencia;
use App\Actions\Fechamento\AprovarCompetencia;
use App\Actions\Fechamento\CalcularFechamentoColaborador;
use App\Enums\AbrangenciaFeriado;
use App\Enums\StatusColaborador;
use App\Enums\TipoCalculoBeneficio;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\ColaboradorObra;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\Fechamento;
use App\Models\Obra;
use App\Models\PessoaJuridica;
use App\Models\Remuneracao;
use App\Models\User;
use App\Services\Fechamento\CalendarioTrabalho;
use App\Services\Fechamento\RateioDoFechamento;
use App\Services\Painel\IndicadoresPainel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Colaborador em várias obras e rateio do que ele recebe (E4, fatia 10). */
class RateioObrasTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->empresa = Empresa::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function obra(string $nome): Obra
    {
        return Obra::factory()->create(['empresa_id' => $this->empresa->id, 'descricao' => $nome]);
    }

    /** Ativo desde 2025, R$ 3.000,00 + vale transporte de R$ 300,00. */
    private function colaborador(array $extra = []): Colaborador
    {
        $c = Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01', 'empresa_id' => $this->empresa->id]);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3000, 'vigencia_inicio' => '2025-01-01']);
        $vt = Beneficio::firstOrCreate(['nome' => 'Vale transporte'], ['tipo_calculo' => TipoCalculoBeneficio::FixoMensal]);
        if (! BeneficioValor::where('beneficio_id', $vt->id)->exists()) {
            BeneficioValor::factory()->create(['beneficio_id' => $vt->id, 'valor' => 300, 'vigencia_inicio' => '2020-01-01']);
        }
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $vt->id, 'inicio' => '2025-01-01']);

        return $c;
    }

    private function ratear(Colaborador $c, array $partes, string $inicio = '2025-01-01'): void
    {
        app(DefinirRateioObras::class)->executar($c, $partes, Carbon::parse($inicio));
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

    // ---- Cadastro do rateio -------------------------------------------------

    public function test_define_o_rateio_e_a_obra_principal_e_a_de_maior_percentual(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();

        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 30], ['obra_id' => $b->id, 'percentual' => '70,00']]);

        $this->assertSame(2, ColaboradorObra::where('colaborador_id', $c->id)->count());
        $this->assertSame($b->id, $c->fresh()->obra_id);
        $this->assertSame('70.00', ColaboradorObra::where('obra_id', $b->id)->value('percentual'));
    }

    public function test_recusa_rateio_invalido(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $outraEmpresa = Obra::factory()->create(['descricao' => 'De fora']);
        $c = $this->colaborador();
        $pj = fn ($partes, $inicio = '2025-06-01') => fn () => $this->ratear($c, $partes, $inicio);

        $this->recusaCom($pj([]), 'ao menos uma obra');
        $this->recusaCom($pj([['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 30]]), 'somar 100%');
        $this->recusaCom($pj([['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $a->id, 'percentual' => 50]]), 'obra repetida');
        $this->recusaCom($pj([['obra_id' => $a->id, 'percentual' => 0], ['obra_id' => $b->id, 'percentual' => 100]]), 'entre 0,01% e 100%');
        $this->recusaCom($pj([['obra_id' => $outraEmpresa->id, 'percentual' => 100]]), 'não pertence à empresa');
        $this->recusaCom($pj([['obra_id' => 99999, 'percentual' => 100]]), 'Obra não encontrada');
        $this->recusaCom($pj([['obra_id' => $a->id, 'percentual' => 100]], '2024-12-01'), 'antes da admissão');
        $this->assertSame(0, ColaboradorObra::count());
    }

    public function test_recusa_comecar_em_mes_fechado(): void
    {
        $a = $this->obra('Alfa');
        $c = $this->colaborador();
        Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada']);

        $this->recusaCom(fn () => $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 100]], '2026-09-15'), 'já foi fechado');
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 100]], '2026-10-01');
        $this->assertSame(1, ColaboradorObra::count());
    }

    public function test_novo_rateio_encerra_o_vigente_na_vespera_e_substitui_o_que_comecaria_depois(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 100]], '2026-01-01');
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $b->id, 'percentual' => 50]], '2026-10-20');

        $this->ratear($c, [['obra_id' => $b->id, 'percentual' => 100]], '2026-10-04');

        $linhas = ColaboradorObra::where('colaborador_id', $c->id)->orderBy('id')->get();
        $this->assertCount(2, $linhas, 'O rateio futuro foi substituído, não somado');
        $this->assertSame('2026-10-04', $linhas[0]->fim->toDateString(), 'O de 100% em Alfa acaba na véspera, com fim exclusivo');
        $this->assertSame($b->id, $linhas[1]->obra_id);
        $this->assertNull($linhas[1]->fim);
        $this->assertSame($b->id, $c->fresh()->obra_id);
    }

    public function test_obra_do_cadastro_vira_rateio_de_100_e_trocar_ou_tirar_a_obra_acompanha(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $pj = PessoaJuridica::factory()->create();
        $dados = fn ($obra) => ['pessoa_fisica_id' => \App\Models\PessoaFisica::factory()->create()->id, 'pessoa_juridica_id' => $pj->id, 'empresa_id' => $this->empresa->id, 'obra_id' => $obra];

        $c = app(SalvarColaborador::class)->executar(null, $dados($a->id));
        $this->assertSame([[$a->id, '100.00']], ColaboradorObra::where('colaborador_id', $c->id)->vigentes()->get()->map(fn ($l) => [$l->obra_id, $l->percentual])->all());

        $c = app(SalvarColaborador::class)->executar($c, ['pessoa_juridica_id' => $pj->id, 'empresa_id' => $this->empresa->id, 'obra_id' => $b->id]);
        $vigentes = ColaboradorObra::where('colaborador_id', $c->id)->vigentes()->get();
        $this->assertSame([$b->id], $vigentes->pluck('obra_id')->all());

        app(SalvarColaborador::class)->executar($c, ['pessoa_juridica_id' => $pj->id, 'empresa_id' => $this->empresa->id, 'obra_id' => null]);
        $this->assertSame(0, ColaboradorObra::where('colaborador_id', $c->id)->vigentes()->count());
    }

    public function test_quem_esta_em_varias_obras_nao_tem_a_obra_trocada_pelo_cadastro(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 40]]);

        $salvo = app(SalvarColaborador::class)->executar($c, ['pessoa_juridica_id' => $c->pessoa_juridica_id, 'empresa_id' => $this->empresa->id, 'obra_id' => $b->id]);

        $this->assertSame($a->id, $salvo->obra_id, 'A obra principal segue o rateio');
        $this->assertSame(2, ColaboradorObra::where('colaborador_id', $c->id)->vigentes()->count());
    }

    public function test_escopo_na_obra_usa_o_rateio_vigente_ou_a_obra_do_cadastro(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $dois = $this->colaborador();
        $this->ratear($dois, [['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $b->id, 'percentual' => 50]]);
        $legado = $this->colaborador(['obra_id' => $b->id]);
        $semObra = $this->colaborador();

        $this->assertEqualsCanonicalizing([$dois->id], Colaborador::naObra($a->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$dois->id, $legado->id], Colaborador::naObra($b->id)->pluck('id')->all());
        $this->assertNotContains($semObra->id, Colaborador::naObra($b->id)->pluck('id')->all());
    }

    // ---- Rateio do fechamento -----------------------------------------------

    public function test_fechamento_e_repartido_pelo_percentual_e_a_soma_bate(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 40]]);

        $competencia = app(AbrirCompetencia::class)->executar('2026-09');
        $f = Fechamento::where('colaborador_id', $c->id)->where('competencia_id', $competencia->id)->firstOrFail();

        $partes = $f->rateios()->orderBy('obra_id')->get();
        $this->assertSame(['3300.00'], [$f->total_liquido]);
        $this->assertSame([$a->id, $b->id], $partes->pluck('obra_id')->all());
        $this->assertSame(['60.0000', '40.0000'], $partes->pluck('percentual')->all());
        $this->assertSame(['1980.00', '1320.00'], $partes->pluck('total_liquido')->all());
        $this->assertSame(['1800.00', '1200.00'], $partes->pluck('valor_remuneracao')->all());
        $this->assertSame(['180.00', '120.00'], $partes->pluck('total_beneficios')->all());
    }

    public function test_mudanca_de_rateio_no_meio_do_mes_pesa_os_dias(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 100]], '2025-01-01');
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $b->id, 'percentual' => 50]], '2026-09-11');

        app(AbrirCompetencia::class)->executar('2026-09');

        $partes = Fechamento::where('colaborador_id', $c->id)->firstOrFail()->rateios()->orderBy('obra_id')->get();
        // 10 dias em 100% Alfa e 20 dias em 50/50: Alfa pesa 20/30 e Beta 10/30.
        $this->assertSame(['66.6667', '33.3333'], $partes->pluck('percentual')->all());
        $this->assertSame(['2200.00', '1100.00'], $partes->pluck('total_liquido')->all());
    }

    public function test_sobra_de_centavos_vai_para_a_maior_parcela(): void
    {
        $c = new Colaborador(['obra_id' => null]);
        $c->id = 1;
        $a = $this->obra('A');
        $b = $this->obra('B');
        $d = $this->obra('D');
        ColaboradorObra::create(['colaborador_id' => $this->colaborador()->id, 'obra_id' => $a->id, 'percentual' => 33.33, 'inicio' => '2025-01-01']);
        $colab = Colaborador::firstOrFail();
        ColaboradorObra::create(['colaborador_id' => $colab->id, 'obra_id' => $b->id, 'percentual' => 33.33, 'inicio' => '2025-01-01']);
        ColaboradorObra::create(['colaborador_id' => $colab->id, 'obra_id' => $d->id, 'percentual' => 33.34, 'inicio' => '2025-01-01']);

        $partes = app(RateioDoFechamento::class)->calcular($colab, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), 10001, 5, -7);

        $this->assertSame(10001, array_sum(array_column($partes, 'remuneracao')));
        $this->assertSame(5, array_sum(array_column($partes, 'beneficios')));
        $this->assertSame(-7, array_sum(array_column($partes, 'ajustes')));
        $this->assertSame(10001 + 5 - 7, array_sum(array_column($partes, 'liquido')));
        $this->assertSame($d->id, $partes[0]['obra_id'], 'A maior parcela vem primeiro e fica com a sobra');
        $this->assertSame(3335, $partes[0]['remuneracao']);
    }

    public function test_sem_rateio_cadastrado_vale_a_obra_do_cadastro_e_sem_obra_vira_nulo(): void
    {
        $a = $this->obra('Alfa');
        $comObra = $this->colaborador(['obra_id' => $a->id]);
        $semObra = $this->colaborador();

        app(AbrirCompetencia::class)->executar('2026-09');

        $r1 = Fechamento::where('colaborador_id', $comObra->id)->firstOrFail()->rateios;
        $r2 = Fechamento::where('colaborador_id', $semObra->id)->firstOrFail()->rateios;
        $this->assertSame([[$a->id, '100.0000', '3300.00']], $r1->map(fn ($r) => [$r->obra_id, $r->percentual, $r->total_liquido])->all());
        $this->assertSame([[null, '100.0000', '3300.00']], $r2->map(fn ($r) => [$r->obra_id, $r->percentual, $r->total_liquido])->all());
    }

    public function test_recalcular_troca_o_rateio_e_aprovar_congela(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 100]]);
        $competencia = app(AbrirCompetencia::class)->executar('2026-09');

        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $b->id, 'percentual' => 50]], '2026-10-01');
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 25], ['obra_id' => $b->id, 'percentual' => 75]], '2025-01-01');
        app(CalcularFechamentoColaborador::class)->executar($c->fresh(), $competencia);
        $f = Fechamento::where('colaborador_id', $c->id)->firstOrFail();
        $this->assertSame(['825.00', '2475.00'], $f->rateios()->orderBy('obra_id')->pluck('total_liquido')->all(), 'Recalcular troca as linhas, sem duplicar');

        $aprovador = User::factory()->create();
        app(AprovarCompetencia::class)->executar($competencia->fresh(), $aprovador);

        $snapshot = $f->fresh()->snapshot['rateio'];
        $this->assertCount(2, $snapshot);
        $this->assertEqualsCanonicalizing(['825.00', '2475.00'], array_column($snapshot, 'total_liquido'));
        $this->recusaCom(fn () => app(CalcularFechamentoColaborador::class)->executar($c->fresh(), $competencia->fresh()), 'já foi aprovada');
        $this->assertSame(2, $f->rateios()->count());
    }

    public function test_feriado_de_qualquer_obra_do_colaborador_conta(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->ratear($c, [['obra_id' => $a->id, 'percentual' => 50], ['obra_id' => $b->id, 'percentual' => 50]]);
        Feriado::factory()->create(['data' => '2026-09-15', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $b->id]);
        $outraObra = $this->obra('Gama');
        Feriado::factory()->create(['data' => '2026-09-16', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $outraObra->id]);

        $dias = app(CalendarioTrabalho::class)->diasDeTrabalho($c->fresh(), Carbon::parse('2026-09-14'), Carbon::parse('2026-09-18'));

        $this->assertSame(['2026-09-14', '2026-09-16', '2026-09-17', '2026-09-18'], array_keys($dias));
    }

    // ---- Relatórios, painel e tela -----------------------------------------

    public function test_custo_por_obra_mostra_a_parte_de_cada_obra_e_o_filtro_enxerga_quem_atua_nela(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $dois = $this->colaborador();
        $this->ratear($dois, [['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 40]]);
        $so = $this->colaborador(['obra_id' => $b->id]);
        $competencia = app(AbrirCompetencia::class)->executar('2026-09');
        app(AprovarCompetencia::class)->executar($competencia, User::factory()->create(), true);
        $this->actingAs($this->usuarioCom('fechamento-list'));

        $linhas = $this->get(route('relatorio.show', 'custo-por-obra'))->viewData('resultado')->linhas;
        $this->assertSame(['Alfa', 1, 'R$ 1.980,00'], [$linhas[0]['obra'], $linhas[0]['colaboradores'], $linhas[0]['liquido']]);
        $this->assertSame(['Beta', 2, 'R$ 4.620,00'], [$linhas[1]['obra'], $linhas[1]['colaboradores'], $linhas[1]['liquido']]);

        $soAlfa = $this->get(route('relatorio.show', ['custo-por-obra', 'obra_id' => $a->id]))->viewData('resultado');
        $this->assertSame(['Alfa'], array_column($soAlfa->linhas, 'obra'));
        $this->assertNotNull($so);
    }

    public function test_painel_conta_o_colaborador_em_cada_obra_dele(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $dois = $this->colaborador();
        $this->ratear($dois, [['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 40]]);
        $this->colaborador(['obra_id' => $b->id]);
        $this->colaborador();
        $rh = $this->usuarioCom('colaborador-list', 'colaborador-edit');

        $porObra = app(IndicadoresPainel::class)->para($rh, now())['por_obra'];

        $this->assertSame([['obra' => 'Beta', 'total' => 2], ['obra' => 'Alfa', 'total' => 1], ['obra' => 'Sem obra', 'total' => 1]], $porObra);
    }

    public function test_tela_salva_o_rateio_e_a_ficha_mostra(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $c = $this->colaborador();
        $this->actingAs($this->usuarioCom('colaborador-list', 'colaborador-edit'));

        $this->postJson(route('colaborador.obras.store', $c->id), ['inicio' => '2026-10-01', 'obras' => [['obra_id' => $a->id, 'percentual' => 70], ['obra_id' => $b->id, 'percentual' => 20]]])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->postJson(route('colaborador.obras.store', $c->id), ['inicio' => '2026-10-01', 'obras' => [['obra_id' => $a->id, 'percentual' => 70], ['obra_id' => $b->id, 'percentual' => 30]]])
            ->assertOk()->assertJson(['success' => true]);

        $this->get(route('colaborador.show', $c->id))->assertOk()
            ->assertSee('Obras e rateio')->assertSee('70,00%')->assertSee('Alfa (70,00%) · Beta (30,00%)', false);
        $this->get(route('colaborador.form', $c->id))->assertOk()->assertSee('disabled', false);
    }

    public function test_conferencia_mostra_o_rateio_so_quando_ha_mais_de_uma_obra(): void
    {
        $a = $this->obra('Alfa');
        $b = $this->obra('Beta');
        $dois = $this->colaborador();
        $this->ratear($dois, [['obra_id' => $a->id, 'percentual' => 60], ['obra_id' => $b->id, 'percentual' => 40]]);
        $so = $this->colaborador(['obra_id' => $a->id]);
        app(AbrirCompetencia::class)->executar('2026-09');
        $this->actingAs($this->usuarioCom('fechamento-list', 'fechamento-conferir'));

        $this->get(route('fechamento.detalhe', Fechamento::where('colaborador_id', $dois->id)->value('id')))
            ->assertOk()->assertSee('Rateio por obra')->assertSee('R$ 1.980,00')->assertSee('R$ 1.320,00')->assertSee('60%');
        $this->get(route('fechamento.detalhe', Fechamento::where('colaborador_id', $so->id)->value('id')))
            ->assertOk()->assertDontSee('Rateio por obra');
    }

    public function test_so_quem_edita_colaborador_define_o_rateio(): void
    {
        $c = $this->colaborador();
        $this->actingAs($this->usuarioCom('colaborador-list'));

        $this->postJson(route('colaborador.obras.store', $c->id), [])->assertForbidden();
        $this->get(route('colaborador.show', $c->id))->assertOk()->assertDontSee('id="form-obras"', false);
    }
}

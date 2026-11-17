<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Remuneracoes\AplicarReajusteLote;
use App\Actions\Remuneracoes\DefinirRemuneracaoInicial;
use App\Actions\Remuneracoes\PreverReajusteLote;
use App\Actions\Remuneracoes\ReajustarRemuneracao;
use App\Enums\FormaReajuste;
use App\Enums\StatusColaborador;
use App\Events\ReajusteAplicado;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\PessoaFisica;
use App\Models\Reajuste;
use App\Models\Remuneracao;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RemuneracaoTest extends TestCase
{
    use RefreshDatabase;

    private function colaborador(array $extra = [], ?string $valor = null, string $inicio = '2026-01-01'): Colaborador
    {
        $c = Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo]);
        if ($valor !== null) {
            app(DefinirRemuneracaoInicial::class)->executar($c, $valor, Carbon::parse($inicio));
        }

        return $c;
    }

    private function valorEm(Colaborador $c, string $dia): ?float
    {
        $v = Remuneracao::vigentesEm($dia)->where('colaborador_id', $c->id)->value('valor_mensal');

        return $v === null ? null : (float) $v;
    }

    public function test_define_a_remuneracao_inicial_uma_unica_vez(): void
    {
        $c = $this->colaborador();
        $acao = app(DefinirRemuneracaoInicial::class);

        $linha = $acao->executar($c, '4500.00', Carbon::parse('2026-02-01'));

        $this->assertSame('4500.00', $linha->valor_mensal);
        $this->assertNull($linha->vigencia_fim);
        $this->assertTrue(Activity::where('event', 'remuneracao-definida')->exists());

        $this->expectException(ValidationException::class);
        $acao->executar($c, '5000', Carbon::parse('2026-03-01'));
    }

    public function test_reajuste_individual_encerra_a_linha_e_cria_outra_sem_editar_a_antiga(): void
    {
        $c = $this->colaborador(valor: '4000.00');
        $antiga = Remuneracao::firstOrFail();

        $reajuste = app(ReajustarRemuneracao::class)->executar($c, FormaReajuste::Percentual, '10', Carbon::parse('2026-05-10'), $this->usuarioCom());

        $this->assertSame(2, Remuneracao::count());
        $this->assertSame('4000.00', $antiga->fresh()->valor_mensal, 'o valor antigo não muda');
        $this->assertSame('2026-05-10', $antiga->fresh()->vigencia_fim->toDateString());
        $this->assertSame(4000.0, $this->valorEm($c, '2026-05-09'), 'a véspera ainda vale o antigo');
        $this->assertSame(4400.0, $this->valorEm($c, '2026-05-10'));
        $this->assertSame(1, Remuneracao::vigentes()->where('colaborador_id', $c->id)->count(), 'nunca duas abertas');
        $this->assertSame($reajuste->id, Remuneracao::vigentes()->first()->reajuste_id);
        $this->assertNotNull($reajuste->aprovado_em);
    }

    public function test_reajuste_em_valor_e_sequencia_de_reajustes(): void
    {
        $c = $this->colaborador(valor: '4000.00');
        $acao = app(ReajustarRemuneracao::class);

        $acao->executar($c, FormaReajuste::Valor, '250.50', Carbon::parse('2026-03-01'));
        $acao->executar($c, FormaReajuste::Percentual, '2', Carbon::parse('2026-09-01'));

        $this->assertSame(4000.0, $this->valorEm($c, '2026-02-28'));
        $this->assertSame(4250.5, $this->valorEm($c, '2026-03-01'));
        $this->assertSame(4335.51, $this->valorEm($c, '2026-09-01'));
        $this->assertSame(3, Remuneracao::where('colaborador_id', $c->id)->count());
    }

    public function test_vigencia_igual_ou_anterior_a_atual_e_recusada(): void
    {
        $c = $this->colaborador(valor: '4000.00', inicio: '2026-05-10');
        $acao = app(ReajustarRemuneracao::class);

        foreach (['2026-05-10', '2026-04-01'] as $dia) {
            try {
                $acao->executar($c, FormaReajuste::Percentual, '5', Carbon::parse($dia));
                $this->fail("Deveria recusar {$dia}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, Remuneracao::count());
        $this->assertSame(0, Reajuste::count(), 'o reajuste recusado não fica gravado');
    }

    public function test_recusa_sem_remuneracao_vigente_valor_zero_e_colaborador_desligado(): void
    {
        $semRemuneracao = $this->colaborador();
        $desligado = $this->colaborador(['status' => StatusColaborador::Desligado]);
        $acao = app(ReajustarRemuneracao::class);

        foreach ([[$semRemuneracao, '5'], [$desligado, '5']] as [$c, $v]) {
            try {
                $acao->executar($c, FormaReajuste::Percentual, $v, Carbon::parse('2026-05-01'));
                $this->fail('Deveria recusar.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(ValidationException::class);
        $acao->executar($this->colaborador(valor: '1000'), FormaReajuste::Percentual, '0', Carbon::parse('2026-05-01'));
    }

    public function test_reajuste_retroativo_a_competencia_fechada_e_recusado(): void
    {
        $c = $this->colaborador(valor: '4000.00', inicio: '2026-01-01');
        $this->app->bind(CompetenciaFechada::class, fn () => new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') <= '2026-06';
            }
        });

        try {
            app(ReajustarRemuneracao::class)->executar($c, FormaReajuste::Percentual, '5', Carbon::parse('2026-06-15'));
            $this->fail('Deveria recusar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('fechada', $e->getMessage());
        }

        app(ReajustarRemuneracao::class)->executar($c, FormaReajuste::Percentual, '5', Carbon::parse('2026-07-01'));
        $this->assertSame(2, Remuneracao::count());
    }

    public function test_dispara_o_evento_depois_do_commit_e_audita_sem_gravar_salario(): void
    {
        Event::fake([ReajusteAplicado::class]);
        $c = $this->colaborador(valor: '4000.00');

        $reajuste = app(ReajustarRemuneracao::class)->executar($c, FormaReajuste::Percentual, '10', Carbon::parse('2026-05-10'));

        Event::assertDispatched(ReajusteAplicado::class, fn ($e) => $e->reajuste->is($reajuste) && $e->remuneracoes->count() === 1);
        $json = Activity::where('event', 'reajuste-aplicado')->firstOrFail()->properties->toJson();
        $this->assertStringNotContainsString('4000', $json);
        $this->assertStringNotContainsString('4400', $json);
    }

    public function test_aviso_de_faixa_nunca_bloqueia(): void
    {
        $cargo = Cargo::factory()->create(['faixa_min' => 3000, 'faixa_max' => 5000]);
        $c = $this->colaborador(['cargo_id' => $cargo->id], valor: '4800.00');

        $this->assertNull($cargo->avisoDeFaixa(480000));
        $this->assertStringContainsString('acima', $cargo->avisoDeFaixa(510000));
        $this->assertStringContainsString('abaixo', $cargo->avisoDeFaixa(250000));

        $this->actingAs($this->usuarioCom('remuneracao-reajustar'))
            ->postJson(route('remuneracao.reajustar', $c->id), ['forma' => 'percentual', 'valor' => '10', 'vigencia' => '2026-06-01'])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'acima da faixa'));
        $this->assertSame(5280.0, $this->valorEm($c, '2026-06-01'));
    }

    // --- lote ---------------------------------------------------------------

    private function lote(): array
    {
        $cargo = Cargo::factory()->create();
        $a = $this->colaborador(['cargo_id' => $cargo->id, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Ana'])->id], '3000.00');
        $b = $this->colaborador(['cargo_id' => $cargo->id, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Bia'])->id], '4000.00');
        $semRemuneracao = $this->colaborador(['cargo_id' => $cargo->id, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Caio'])->id]);
        $outroCargo = $this->colaborador(['cargo_id' => Cargo::factory()->create()->id], '9000.00');

        return [$cargo, $a, $b, $semRemuneracao, $outroCargo];
    }

    public function test_previa_por_cargo_mostra_atual_novo_diferenca_e_ignora_sem_remuneracao(): void
    {
        [$cargo, $a, $b, $semRemuneracao] = $this->lote();

        $previa = app(PreverReajusteLote::class)->executar(['tipo' => 'cargo', 'cargo_id' => $cargo->id], FormaReajuste::Percentual, '10', Carbon::parse('2026-07-01'));

        $this->assertCount(2, $previa['linhas']);
        $this->assertSame([300000, 330000, 30000], [$previa['linhas'][0]['atual'], $previa['linhas'][0]['novo'], $previa['linhas'][0]['diferenca']]);
        $this->assertSame(700000, $previa['total_atual']);
        $this->assertSame(770000, $previa['total_novo']);
        $this->assertSame(0, $previa['erros']);
        $this->assertSame(['Caio'], array_column($previa['ignorados'], 'nome'));
        $this->assertSame(1, Remuneracao::vigentes()->where('colaborador_id', $a->id)->count(), 'a prévia não grava nada');
        $this->assertSame(0, Reajuste::count());
    }

    public function test_previa_por_lista_so_com_os_escolhidos_abertos(): void
    {
        [, $a, , , $outro] = $this->lote();
        $desligado = $this->colaborador(valor: '2000');
        $desligado->update(['status' => StatusColaborador::Desligado]);

        $previa = app(PreverReajusteLote::class)->executar(['tipo' => 'lista', 'colaborador_ids' => [$a->id, $outro->id, $desligado->id]], FormaReajuste::Valor, '100', Carbon::parse('2026-07-01'));

        $this->assertEqualsCanonicalizing([$a->id, $outro->id], array_column($previa['linhas'], 'colaborador_id'));
    }

    public function test_previa_marca_erro_quando_a_vigencia_nao_e_posterior(): void
    {
        [$cargo] = $this->lote();

        $previa = app(PreverReajusteLote::class)->executar(['tipo' => 'cargo', 'cargo_id' => $cargo->id], FormaReajuste::Percentual, '10', Carbon::parse('2026-01-01'));

        $this->assertSame(2, $previa['erros']);
        $this->assertStringContainsString('posterior', $previa['linhas'][0]['erro']);
    }

    public function test_aplica_o_lote_com_o_hash_da_previa_e_grava_tudo_de_uma_vez(): void
    {
        Event::fake([ReajusteAplicado::class]);
        [$cargo, $a, $b, $semRemuneracao, $outro] = $this->lote();
        $criterio = ['tipo' => 'cargo', 'cargo_id' => $cargo->id];
        $vigencia = Carbon::parse('2026-07-01');

        $hash = app(PreverReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia)['hash'];
        $aprovador = $this->usuarioCom();
        $reajuste = app(AplicarReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia, $hash, $aprovador);

        $this->assertSame(3300.0, $this->valorEm($a, '2026-07-01'));
        $this->assertSame(4400.0, $this->valorEm($b, '2026-07-01'));
        $this->assertSame(3000.0, $this->valorEm($a, '2026-06-30'));
        $this->assertNull($this->valorEm($semRemuneracao, '2026-07-01'));
        $this->assertSame(9000.0, $this->valorEm($outro, '2026-07-01'), 'outro cargo fica de fora');
        $this->assertSame(2, $reajuste->remuneracoes()->count());
        $this->assertSame('lote', $reajuste->tipo->value);
        $this->assertSame($aprovador->id, $reajuste->aprovado_por);
        $this->assertSame(['tipo' => 'cargo', 'cargo_id' => $cargo->id], $reajuste->criterio);
        Event::assertDispatched(ReajusteAplicado::class, fn ($e) => $e->remuneracoes->count() === 2);
    }

    public function test_hash_diferente_recusa_e_nada_e_gravado(): void
    {
        [$cargo, $a] = $this->lote();
        $criterio = ['tipo' => 'cargo', 'cargo_id' => $cargo->id];
        $vigencia = Carbon::parse('2026-07-01');
        $hash = app(PreverReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia)['hash'];

        // Alguém reajusta a Ana depois de a prévia ser vista.
        app(ReajustarRemuneracao::class)->executar($a, FormaReajuste::Valor, '500', Carbon::parse('2026-06-01'));
        $antes = Remuneracao::count();

        try {
            app(AplicarReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia, $hash, $this->usuarioCom());
            $this->fail('Deveria recusar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('mudaram', $e->getMessage());
        }

        $this->assertSame($antes, Remuneracao::count());
        $this->assertSame(1, Reajuste::count(), 'só o individual de antes');
    }

    public function test_lote_com_linha_com_erro_nao_e_aplicado(): void
    {
        [$cargo] = $this->lote();
        $criterio = ['tipo' => 'cargo', 'cargo_id' => $cargo->id];
        $vigencia = Carbon::parse('2026-01-01');
        $hash = app(PreverReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia)['hash'];

        $this->expectException(ValidationException::class);
        try {
            app(AplicarReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia, $hash, $this->usuarioCom());
        } finally {
            $this->assertSame(0, Reajuste::count());
            $this->assertSame(3, Remuneracao::count());
        }
    }

    public function test_lote_e_tudo_ou_nada_quando_uma_troca_falha_no_meio(): void
    {
        [$cargo, $a, $b] = $this->lote();
        $criterio = ['tipo' => 'cargo', 'cargo_id' => $cargo->id];
        $vigencia = Carbon::parse('2026-07-01');
        $hash = app(PreverReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia)['hash'];

        // A competência passa a estar fechada só depois da prévia: a gravação recusa tudo.
        $this->app->bind(CompetenciaFechada::class, fn () => new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return true;
            }
        });

        try {
            app(AplicarReajusteLote::class)->executar($criterio, FormaReajuste::Percentual, '10', $vigencia, $hash, $this->usuarioCom());
            $this->fail('Deveria recusar.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, Reajuste::count());
        $this->assertSame(3000.0, $this->valorEm($a, '2026-07-01'));
        $this->assertSame(4000.0, $this->valorEm($b, '2026-07-01'));
    }

    public function test_telas_do_lote_previa_e_aplicacao(): void
    {
        [$cargo, $a] = $this->lote();
        $rh = $this->usuarioCom('remuneracao-reajustar');
        $corpo = ['tipo' => 'cargo', 'cargo_id' => $cargo->id, 'forma' => 'percentual', 'valor' => '10', 'vigencia' => '2026-07-01'];

        $this->actingAs($rh)->get(route('reajuste.lote'))->assertOk()->assertSee('Reajuste em lote');

        $previa = $this->postJson(route('reajuste.lote.previa'), $corpo)->assertOk()->assertJsonPath('data.erros', 0)->json('data');
        $this->assertCount(2, $previa['linhas']);
        $this->assertSame('R$ 3.300,00', $previa['linhas'][0]['novo']);
        $this->assertSame('R$ 7.700,00', $previa['total_novo']);

        $this->postJson(route('reajuste.lote.aplicar'), $corpo + ['hash' => 'errado'])->assertStatus(422);
        $this->postJson(route('reajuste.lote.aplicar'), $corpo + ['hash' => $previa['hash']])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, '2 colaborador'));

        $this->assertSame(3300.0, $this->valorEm($a, '2026-07-01'));
        $this->postJson(route('reajuste.lote.previa'), ['tipo' => 'cargo'])->assertStatus(422);
    }

    public function test_remuneracao_so_aparece_no_detalhe_para_quem_tem_remuneracao_list(): void
    {
        $c = $this->colaborador(['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Zélia'])->id], '4321.00');

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('colaborador.show', $c->id))
            ->assertOk()->assertDontSee('4.321,00')->assertDontSee('Remuneração');
        $this->actingAs($this->usuarioCom('colaborador-list', 'remuneracao-list'))->get(route('colaborador.show', $c->id))
            ->assertOk()->assertSee('R$ 4.321,00');
    }

    public function test_define_e_reajusta_pelo_detalhe(): void
    {
        $c = $this->colaborador();
        $rh = $this->usuarioCom('remuneracao-reajustar');

        $this->actingAs($rh)->postJson(route('remuneracao.definir', $c->id), ['valor' => '3500', 'vigencia' => '2026-02-01'])->assertOk();
        $this->postJson(route('remuneracao.definir', $c->id), ['valor' => '3600', 'vigencia' => '2026-03-01'])->assertStatus(422);
        $this->postJson(route('remuneracao.reajustar', $c->id), ['forma' => 'percentual', 'valor' => '0', 'vigencia' => '2026-04-01'])->assertStatus(422);
        $this->postJson(route('remuneracao.reajustar', $c->id), ['forma' => 'percentual', 'valor' => '4', 'vigencia' => '2026-04-01'])->assertOk();

        $this->assertSame(3640.0, $this->valorEm($c, '2026-04-01'));
    }
}

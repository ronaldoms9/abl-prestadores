<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Beneficios\AplicarReajusteBeneficios;
use App\Actions\Beneficios\DefinirValorBeneficio;
use App\Actions\Beneficios\PreverReajusteBeneficios;
use App\Enums\FormaReajuste;
use App\Enums\TipoCalculoBeneficio;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\ColaboradorBeneficio;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BeneficioTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_do_beneficio_com_auditoria(): void
    {
        $this->actingAs($this->usuarioCom('beneficio-list', 'beneficio-gerir'));

        $this->postJson(route('beneficio.store'), ['nome' => 'Vale alimentação', 'tipo_calculo' => 'por_dia_trabalhado'])->assertOk();
        $beneficio = Beneficio::firstOrFail();
        $this->assertSame(TipoCalculoBeneficio::PorDiaTrabalhado, $beneficio->tipo_calculo);
        $this->assertTrue(Activity::where('event', 'beneficio-criado')->exists());

        $this->postJson(route('beneficio.store', $beneficio->id), ['nome' => 'Vale alimentação', 'tipo_calculo' => 'fixo_mensal', 'ativo' => 0])->assertOk();
        $this->assertSame(TipoCalculoBeneficio::FixoMensal, $beneficio->fresh()->tipo_calculo);
        $this->assertFalse($beneficio->fresh()->ativo);
    }

    public function test_nome_e_tipo_sao_validados(): void
    {
        $this->actingAs($this->usuarioCom('beneficio-gerir'));
        Beneficio::factory()->create(['nome' => 'Plano de saúde']);

        $this->postJson(route('beneficio.store'), ['nome' => 'Plano de saúde', 'tipo_calculo' => 'fixo_mensal'])->assertStatus(422);
        $this->postJson(route('beneficio.store'), ['nome' => 'Outro', 'tipo_calculo' => 'invalido'])->assertStatus(422);
        $this->postJson(route('beneficio.store'), ['nome' => '', 'tipo_calculo' => 'fixo_mensal'])->assertStatus(422);
    }

    public function test_nao_exclui_beneficio_vinculado_a_colaborador(): void
    {
        $vinculado = ColaboradorBeneficio::factory()->create()->beneficio;
        $livre = Beneficio::factory()->create();

        $this->actingAs($this->usuarioCom('beneficio-gerir'))->deleteJson(route('beneficio.delete', $vinculado->id))->assertStatus(400);
        $this->assertNotSoftDeleted($vinculado);
        $this->deleteJson(route('beneficio.delete', $livre->id))->assertStatus(201);
        $this->assertSoftDeleted($livre);
    }

    public function test_lista_mostra_o_valor_vigente(): void
    {
        $beneficio = Beneficio::factory()->create(['nome' => 'Vale transporte']);
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 300, 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-06-01']);
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 350, 'vigencia_inicio' => '2026-06-01']);

        $linha = $this->actingAs($this->usuarioCom('beneficio-list'))
            ->getJson(route('beneficio.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');

        $this->assertSame('Vale transporte', $linha['nome']);
        $this->assertSame('R$ 350,00', $linha['valor_atual']);
        $this->assertSame('Fixo mensal', $linha['tipo']);
    }

    public function test_define_o_primeiro_valor_e_a_troca_encerra_a_linha_aberta(): void
    {
        $beneficio = Beneficio::factory()->create();
        $definir = app(DefinirValorBeneficio::class);

        $primeira = $definir->executar($beneficio, '400', Carbon::parse('2026-03-01'));
        $segunda = $definir->executar($beneficio, '450,50', Carbon::parse('2026-05-10'));

        $this->assertSame('2026-05-10', $primeira->fresh()->vigencia_fim->toDateString());
        $this->assertNull($segunda->fresh()->vigencia_fim);
        $this->assertSame('450.50', $segunda->fresh()->valor);
        $this->assertSame(1, BeneficioValor::where('beneficio_id', $beneficio->id)->vigentes()->count());
        $this->assertSame(2, Activity::where('event', 'beneficio-valor-definido')->count());
    }

    public function test_recusa_valor_nao_positivo_e_vigencia_nao_posterior(): void
    {
        $beneficio = Beneficio::factory()->create();
        $definir = app(DefinirValorBeneficio::class);
        $definir->executar($beneficio, '400', Carbon::parse('2026-03-01'));

        foreach ([['0', '2026-04-01'], ['-5', '2026-04-01'], ['500', '2026-03-01'], ['500', '2026-02-01']] as [$valor, $vigencia]) {
            try {
                $definir->executar($beneficio, $valor, Carbon::parse($vigencia));
                $this->fail("Aceitou {$valor} em {$vigencia}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, BeneficioValor::where('beneficio_id', $beneficio->id)->count());
    }

    public function test_recusa_mudanca_de_valor_em_competencia_fechada(): void
    {
        $this->app->instance(CompetenciaFechada::class, new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === '2026-04';
            }
        });
        $beneficio = Beneficio::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('fechada');
        app(DefinirValorBeneficio::class)->executar($beneficio, '400', Carbon::parse('2026-04-10'));
    }

    public function test_tela_define_valor_e_mostra_erro_de_vigencia(): void
    {
        $beneficio = Beneficio::factory()->create();
        $this->actingAs($this->usuarioCom('beneficio-list', 'beneficio-gerir'));

        $this->postJson(route('beneficio.valor.store', $beneficio->id), ['valor' => '300', 'vigencia' => '2026-03-01'])->assertOk();
        $this->postJson(route('beneficio.valor.store', $beneficio->id), ['valor' => '320', 'vigencia' => '2026-03-01'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->getJson(route('beneficio.valores', $beneficio->id))->assertOk()->assertSee('01/03/2026');
    }

    public function test_previa_do_lote_calcula_sem_gravar_e_separa_quem_nao_tem_valor(): void
    {
        $a = Beneficio::factory()->create(['nome' => 'A']);
        $b = Beneficio::factory()->create(['nome' => 'B']);
        $semValor = Beneficio::factory()->create(['nome' => 'C']);
        BeneficioValor::factory()->create(['beneficio_id' => $a->id, 'valor' => 400, 'vigencia_inicio' => '2026-01-01']);
        BeneficioValor::factory()->create(['beneficio_id' => $b->id, 'valor' => 100, 'vigencia_inicio' => '2026-01-01']);

        $previa = app(PreverReajusteBeneficios::class)->executar([$a->id, $b->id, $semValor->id], FormaReajuste::Percentual, '10', Carbon::parse('2026-06-01'));

        $this->assertSame([44000, 11000], array_column($previa['linhas'], 'novo'));
        $this->assertSame(50000, $previa['total_atual']);
        $this->assertSame(55000, $previa['total_novo']);
        $this->assertSame(0, $previa['erros']);
        $this->assertSame('C', $previa['ignorados'][0]['nome']);
        $this->assertSame(2, BeneficioValor::count());
    }

    public function test_aplica_o_lote_com_hash_e_grava_tudo_ou_nada(): void
    {
        $a = Beneficio::factory()->create();
        $b = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $a->id, 'valor' => 400, 'vigencia_inicio' => '2026-01-01']);
        BeneficioValor::factory()->create(['beneficio_id' => $b->id, 'valor' => 100, 'vigencia_inicio' => '2026-01-01']);
        $vigencia = Carbon::parse('2026-06-01');
        $previa = app(PreverReajusteBeneficios::class)->executar([$a->id, $b->id], FormaReajuste::Valor, '25', $vigencia);

        try {
            app(AplicarReajusteBeneficios::class)->executar([$a->id, $b->id], FormaReajuste::Valor, '25', $vigencia, 'hash-errado');
            $this->fail('Aceitou hash diferente da prévia');
        } catch (ValidationException) {
            $this->assertSame(2, BeneficioValor::count());
        }

        $novas = app(AplicarReajusteBeneficios::class)->executar([$a->id, $b->id], FormaReajuste::Valor, '25', $vigencia, $previa['hash']);

        $this->assertCount(2, $novas);
        $this->assertSame(4, BeneficioValor::count());
        $this->assertSame('425.00', BeneficioValor::where('beneficio_id', $a->id)->vigentes()->first()->valor);
        $this->assertSame('125.00', BeneficioValor::where('beneficio_id', $b->id)->vigentes()->first()->valor);
        $this->assertTrue(Activity::where('event', 'beneficio-reajuste-aplicado')->exists());
    }

    public function test_lote_recusa_vigencia_que_nao_e_posterior_ao_valor_atual(): void
    {
        $a = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $a->id, 'valor' => 400, 'vigencia_inicio' => '2026-06-01']);

        $previa = app(PreverReajusteBeneficios::class)->executar([$a->id], FormaReajuste::Valor, '10', Carbon::parse('2026-06-01'));
        $this->assertSame(1, $previa['erros']);

        $this->expectException(ValidationException::class);
        app(AplicarReajusteBeneficios::class)->executar([$a->id], FormaReajuste::Valor, '10', Carbon::parse('2026-06-01'), $previa['hash']);
    }

    public function test_tela_do_lote_previa_e_aplica(): void
    {
        $a = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $a->id, 'valor' => 200, 'vigencia_inicio' => '2026-01-01']);
        $this->actingAs($this->usuarioCom('beneficio-gerir'));
        $dados = ['beneficio_ids' => [$a->id], 'forma' => 'percentual', 'valor' => '5', 'vigencia' => '2026-07-01'];

        $this->get(route('reajuste.beneficios'))->assertOk();
        $hash = $this->postJson(route('reajuste.beneficios.previa'), $dados)->assertOk()->json('data.hash');
        $this->postJson(route('reajuste.beneficios.aplicar'), $dados + ['hash' => $hash])->assertOk();

        $this->assertSame('210.00', BeneficioValor::where('beneficio_id', $a->id)->vigentes()->first()->valor);
    }
}

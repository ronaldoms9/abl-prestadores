<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Beneficios\AplicarBeneficiosPadrao;
use App\Actions\Beneficios\BeneficioVigente;
use App\Actions\Beneficios\EncerrarBeneficioColaborador;
use App\Actions\Beneficios\SalvarBeneficiosPadraoCargo;
use App\Actions\Beneficios\VincularBeneficio;
use App\Enums\StatusColaborador;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Cargo;
use App\Models\CargoBeneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BeneficioColaboradorTest extends TestCase
{
    use RefreshDatabase;

    private function colaboradorComBeneficio(?string $personalizado = null): array
    {
        $colaborador = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $beneficio = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 400, 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-05-10']);
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 450, 'vigencia_inicio' => '2026-05-10']);
        ColaboradorBeneficio::factory()->create([
            'colaborador_id' => $colaborador->id, 'beneficio_id' => $beneficio->id,
            'valor_personalizado' => $personalizado, 'inicio' => '2026-02-01',
        ]);

        return [$colaborador, $beneficio];
    }

    public function test_valor_efetivo_usa_a_tabela_vigente_no_dia_e_troca_no_meio_do_mes(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();
        $vigente = app(BeneficioVigente::class);

        $this->assertSame(40000, $vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-05-09')));
        $this->assertSame(45000, $vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-05-10')));
        $this->assertSame(45000, $vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-05-31')));
    }

    public function test_valor_personalizado_vale_sobre_a_tabela(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio('300.00');

        $this->assertSame(30000, app(BeneficioVigente::class)->valorEm($colaborador, $beneficio, Carbon::parse('2026-03-15')));
        $this->assertSame(30000, app(BeneficioVigente::class)->valorEm($colaborador, $beneficio, Carbon::parse('2026-06-15')));
    }

    public function test_sem_vinculo_no_dia_ou_sem_valor_na_tabela_o_valor_e_nulo(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();
        $vigente = app(BeneficioVigente::class);

        $this->assertNull($vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-01-31')), 'antes do vínculo');
        $this->assertNull($vigente->valorEm($colaborador, $beneficio, Carbon::parse('2025-12-31')), 'antes da tabela');

        $outro = Beneficio::factory()->create();
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $colaborador->id, 'beneficio_id' => $outro->id, 'inicio' => '2026-01-01']);
        $this->assertNull($vigente->valorEm($colaborador, $outro, Carbon::parse('2026-03-01')), 'benefício sem valor na tabela');
    }

    public function test_vinculo_encerrado_nao_vale_no_dia_do_fim(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();
        ColaboradorBeneficio::where('colaborador_id', $colaborador->id)->update(['fim' => '2026-06-01']);
        $vigente = app(BeneficioVigente::class);

        $this->assertSame(45000, $vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-05-31')));
        $this->assertNull($vigente->valorEm($colaborador, $beneficio, Carbon::parse('2026-06-01')));
    }

    public function test_lista_do_dia_traz_valor_e_origem(): void
    {
        [$colaborador] = $this->colaboradorComBeneficio('300');
        $segundo = Beneficio::factory()->create(['nome' => 'Plano de saúde']);
        BeneficioValor::factory()->create(['beneficio_id' => $segundo->id, 'valor' => 120, 'vigencia_inicio' => '2026-01-01']);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $colaborador->id, 'beneficio_id' => $segundo->id, 'inicio' => '2026-01-01']);

        $lista = app(BeneficioVigente::class)->listaEm($colaborador, Carbon::parse('2026-06-01'));

        $this->assertCount(2, $lista);
        $plano = $lista->firstWhere(fn ($i) => $i['beneficio']->is($segundo));
        $this->assertSame([12000, false], [$plano['valor'], $plano['personalizado']]);
        $this->assertSame([30000, true], [$lista->firstWhere(fn ($i) => ! $i['beneficio']->is($segundo))['valor'], true]);
    }

    public function test_vincula_com_auditoria_e_recusa_periodo_sobreposto(): void
    {
        $colaborador = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $beneficio = Beneficio::factory()->create();
        $vincular = app(VincularBeneficio::class);

        $primeiro = $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-03-01'), '250,00');
        $this->assertSame('250.00', $primeiro->fresh()->valor_personalizado);
        $this->assertTrue(Activity::where('event', 'colaborador-beneficio-vinculado')->exists());

        $this->expectException(ValidationException::class);
        $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-04-01'));
    }

    public function test_permite_vinculo_novo_depois_do_encerramento_e_antes_de_um_futuro(): void
    {
        $colaborador = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $beneficio = Beneficio::factory()->create();
        $vincular = app(VincularBeneficio::class);

        $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-03-01'), null, Carbon::parse('2026-04-01'));
        $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-04-01'), null, Carbon::parse('2026-05-01'));
        $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-06-01'));

        $this->assertSame(3, ColaboradorBeneficio::count());

        $this->expectException(ValidationException::class);
        $vincular->executar($colaborador, $beneficio, Carbon::parse('2026-04-15'), null, Carbon::parse('2026-06-15'));
    }

    public function test_recusa_vinculo_invalido(): void
    {
        $ativo = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $desligado = Colaborador::factory()->create(['status' => StatusColaborador::Desligado, 'data_desligamento' => '2026-05-01']);
        $beneficio = Beneficio::factory()->create();
        $inativo = Beneficio::factory()->create(['ativo' => false]);
        $vincular = app(VincularBeneficio::class);

        $casos = [
            'desligado' => fn () => $vincular->executar($desligado, $beneficio, Carbon::parse('2026-01-01')),
            'depois do desligamento' => fn () => $vincular->executar(Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_desligamento' => '2026-05-01']), $beneficio, Carbon::parse('2026-05-02')),
            'benefício inativo' => fn () => $vincular->executar($ativo, $inativo, Carbon::parse('2026-01-01')),
            'valor zero' => fn () => $vincular->executar($ativo, $beneficio, Carbon::parse('2026-01-01'), '0'),
            'fim antes do início' => fn () => $vincular->executar($ativo, $beneficio, Carbon::parse('2026-03-01'), null, Carbon::parse('2026-02-01')),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Aceitou: {$nome}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, ColaboradorBeneficio::count());
    }

    public function test_recusa_vinculo_e_encerramento_em_competencia_fechada(): void
    {
        $this->app->instance(CompetenciaFechada::class, new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === '2026-03';
            }
        });
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();

        try {
            app(VincularBeneficio::class)->executar($colaborador, Beneficio::factory()->create(), Carbon::parse('2026-03-10'));
            $this->fail('Vinculou em competência fechada');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);
        app(EncerrarBeneficioColaborador::class)->executar(ColaboradorBeneficio::firstOrFail(), Carbon::parse('2026-03-20'));
    }

    public function test_encerra_o_vinculo_na_data_e_recusa_encerrar_duas_vezes_ou_antes_do_inicio(): void
    {
        [$colaborador] = $this->colaboradorComBeneficio();
        $vinculo = ColaboradorBeneficio::firstOrFail();
        $encerrar = app(EncerrarBeneficioColaborador::class);

        try {
            $encerrar->executar($vinculo, Carbon::parse('2026-02-01'));
            $this->fail('Encerrou no dia do início');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $encerrar->executar($vinculo, Carbon::parse('2026-07-01'));
        $this->assertSame('2026-07-01', $vinculo->fresh()->fim->toDateString());
        $this->assertTrue(Activity::where('event', 'colaborador-beneficio-encerrado')->exists());

        $this->expectException(ValidationException::class);
        $encerrar->executar($vinculo->fresh(), Carbon::parse('2026-08-01'));
    }

    public function test_beneficios_padrao_do_cargo_sao_trocados_como_conjunto(): void
    {
        $cargo = Cargo::factory()->create();
        [$a, $b, $c] = Beneficio::factory()->count(3)->create();
        $salvar = app(SalvarBeneficiosPadraoCargo::class);

        $salvar->executar($cargo, [$a->id, $b->id]);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $cargo->beneficiosPadrao()->pluck('beneficio_id')->all());

        $salvar->executar($cargo, [$b->id, $c->id, 9999]);
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $cargo->beneficiosPadrao()->pluck('beneficio_id')->all());

        // Volta um removido antes: restaura a linha apagada em vez de colidir com o índice único.
        $salvar->executar($cargo, [$a->id]);
        $this->assertSame([$a->id], $cargo->beneficiosPadrao()->pluck('beneficio_id')->all());
        $this->assertSame(1, CargoBeneficio::where('cargo_id', $cargo->id)->where('beneficio_id', $a->id)->count());
    }

    public function test_aplica_os_padroes_do_cargo_sem_duplicar_nem_pegar_inativos(): void
    {
        $cargo = Cargo::factory()->create();
        $vt = Beneficio::factory()->create();
        $va = Beneficio::factory()->create();
        $inativo = Beneficio::factory()->create(['ativo' => false]);
        foreach ([$vt, $va, $inativo] as $beneficio) {
            CargoBeneficio::factory()->create(['cargo_id' => $cargo->id, 'beneficio_id' => $beneficio->id]);
        }
        $colaborador = Colaborador::factory()->create(['cargo_id' => $cargo->id, 'status' => StatusColaborador::Ativo]);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $colaborador->id, 'beneficio_id' => $vt->id, 'inicio' => '2026-01-01']);

        $criados = app(AplicarBeneficiosPadrao::class)->executar($colaborador, Carbon::parse('2026-06-01'));

        $this->assertSame([$va->id], $criados->pluck('beneficio_id')->all());
        $this->assertSame('2026-06-01', $criados->first()->inicio->toDateString());
        $this->assertCount(0, app(AplicarBeneficiosPadrao::class)->executar($colaborador, Carbon::parse('2026-06-01')));
        $this->assertCount(0, app(AplicarBeneficiosPadrao::class)->executar(Colaborador::factory()->create(['cargo_id' => null]), Carbon::parse('2026-06-01')));
    }

    public function test_tela_do_colaborador_mostra_beneficios_e_vincula_e_encerra(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();
        $novo = Beneficio::factory()->create(['nome' => 'Seguro de vida']);
        $this->actingAs($this->usuarioCom('colaborador-list', 'beneficio-list', 'colaborador-beneficio-edit', 'colaborador-edit'));

        $this->get(route('colaborador.show', $colaborador->id))->assertOk()->assertSee($beneficio->nome)->assertSee('R$ 450,00');

        $this->postJson(route('colaborador.beneficio.store', $colaborador->id), ['beneficio_id' => $novo->id, 'inicio' => '2026-06-01', 'valor_personalizado' => '80'])->assertOk();
        $vinculo = ColaboradorBeneficio::where('beneficio_id', $novo->id)->firstOrFail();
        $this->assertSame('80.00', $vinculo->valor_personalizado);

        $this->postJson(route('colaborador.beneficio.store', $colaborador->id), ['beneficio_id' => $novo->id, 'inicio' => '2026-07-01'])->assertStatus(422);
        $this->postJson(route('colaborador.beneficio.encerrar', $vinculo->id), ['fim' => '2026-08-01'])->assertOk();
        $this->assertSame('2026-08-01', $vinculo->fresh()->fim->toDateString());
    }

    public function test_quem_nao_ve_beneficios_nao_ve_a_secao(): void
    {
        [$colaborador, $beneficio] = $this->colaboradorComBeneficio();

        $this->actingAs($this->usuarioCom('colaborador-list'))
            ->get(route('colaborador.show', $colaborador->id))->assertOk()->assertDontSee($beneficio->nome);
    }

    public function test_formulario_do_cargo_salva_os_beneficios_padrao_so_para_quem_gere_beneficios(): void
    {
        $cargo = Cargo::factory()->create(['nome' => 'Pedreiro']);
        $beneficio = Beneficio::factory()->create();

        $this->actingAs($this->usuarioCom('cargo-edit', 'beneficio-gerir'))
            ->postJson(route('cargo.store', $cargo->id), ['nome' => 'Pedreiro', 'beneficios_padrao_enviado' => 1, 'beneficios_padrao' => [$beneficio->id]])->assertOk();
        $this->assertSame([$beneficio->id], $cargo->beneficiosPadrao()->pluck('beneficio_id')->all());

        // Quem só edita cargo não mexe nos padrões, mesmo que mande o campo.
        $this->actingAs($this->usuarioCom('cargo-edit'))
            ->postJson(route('cargo.store', $cargo->id), ['nome' => 'Pedreiro', 'beneficios_padrao_enviado' => 1, 'beneficios_padrao' => []])->assertOk();
        $this->assertSame([$beneficio->id], $cargo->beneficiosPadrao()->pluck('beneficio_id')->all());

        $this->actingAs($this->usuarioCom('cargo-edit', 'beneficio-gerir'))
            ->get(route('cargo.form', $cargo->id))->assertOk()->assertSee('Benefícios padrão do cargo');
    }
}

<?php

namespace Tests\Feature\Desligamento;

use App\Actions\Beneficios\VincularBeneficio;
use App\Actions\Cadastros\SalvarColaborador;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Desligamento\DesligarColaborador;
use App\Actions\Desligamento\PreviaDesligamento;
use App\Actions\Remuneracoes\ReajustarRemuneracao;
use App\Enums\FormaReajuste;
use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\ColaboradorEquipe;
use App\Models\Configuracao;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\Equipe;
use App\Models\PessoaFisica;
use App\Models\Remuneracao;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Contratos\GeradorPdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DesligamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Storage::fake('local');
        $this->app->bind(GeradorPdf::class, fn () => new class implements GeradorPdf
        {
            public function gerar(string $html): string
            {
                return '%PDF';
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ativo(string $valor = '3000', string $admissao = '2026-01-05', bool $comContrato = false): Colaborador
    {
        $colaborador = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => $admissao]);
        Remuneracao::factory()->create(['colaborador_id' => $colaborador->id, 'valor_mensal' => $valor, 'vigencia_inicio' => $admissao]);

        if ($comContrato) {
            Contrato::factory()->gerado('2026/'.str_pad((string) (Contrato::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT))->create([
                'colaborador_id' => $colaborador->id, 'status' => StatusContrato::Assinado, 'vigencia_inicio' => $admissao,
            ]);
        }

        return $colaborador;
    }

    private function modeloDistrato(string $corpo = '<p>Distrato {{contrato.numero}} de {{colaborador.nome}} em {{desligamento.data}}: {{desligamento.motivo}}</p>'): ContratoModelo
    {
        return ContratoModelo::factory()->create(['tipo' => TipoContrato::Distrato, 'nome' => 'Distrato padrão', 'corpo_html' => $corpo]);
    }

    private function desligar(Colaborador $c, string $data = '2026-11-10', string $motivo = 'Fim da obra', bool $distrato = false, ?string $obs = null): array
    {
        return app(DesligarColaborador::class)->executar($c, Carbon::parse($data), $motivo, $obs, $distrato);
    }

    public function test_previa_traz_remuneracao_proporcional_e_beneficios_fixos_proporcionais(): void
    {
        $c = $this->ativo('3000', '2026-01-05');
        $vt = Beneficio::factory()->create(['nome' => 'Plano de saúde']);
        $va = Beneficio::factory()->porDia()->create(['nome' => 'Vale alimentação']);
        foreach ([$vt, $va] as $b) {
            BeneficioValor::factory()->create(['beneficio_id' => $b->id, 'valor' => 600, 'vigencia_inicio' => '2026-01-01']);
            ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id, 'inicio' => '2026-01-05']);
        }

        $r = app(PreviaDesligamento::class)->executar($c, Carbon::parse('2026-11-10'));

        $this->assertSame(['2026-11-01', '2026-11-10', 10], [$r['inicio']->toDateString(), $r['fim']->toDateString(), $r['dias']]);
        $this->assertSame(100000, $r['remuneracao'], '3000 ÷ 30 × 10');
        $this->assertSame([['nome' => 'Plano de saúde', 'valor_mensal' => 60000, 'valor' => 20000, 'personalizado' => false]], $r['beneficios'], 'só o fixo, proporcional');
        $this->assertSame([20000, 120000], [$r['total_beneficios'], $r['total']]);
        $this->assertContains(PreviaDesligamento::AVISO, $r['avisos']);
    }

    public function test_beneficio_fixo_pode_ser_pago_cheio_pela_configuracao(): void
    {
        Configuracao::definir('calculo.beneficio_fixo_proporcional', false);
        $c = $this->ativo();
        $b = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $b->id, 'valor' => 600, 'vigencia_inicio' => '2026-01-01']);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id, 'valor_personalizado' => 450, 'inicio' => '2026-01-05']);

        $r = app(PreviaDesligamento::class)->executar($c, Carbon::parse('2026-11-10'));

        $this->assertSame([45000, true], [$r['beneficios'][0]['valor'], $r['beneficios'][0]['personalizado']]);
    }

    public function test_previa_de_quem_foi_admitido_no_mes_conta_so_a_partir_da_admissao(): void
    {
        $c = $this->ativo('3000', '2026-11-04');
        $b = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $b->id, 'valor' => 300, 'vigencia_inicio' => '2026-01-01']);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id, 'inicio' => '2026-11-06']);

        $r = app(PreviaDesligamento::class)->executar($c, Carbon::parse('2026-11-13'));

        $this->assertSame(['2026-11-04', 10], [$r['inicio']->toDateString(), $r['dias']]);
        $this->assertSame(100000, $r['remuneracao']);
        $this->assertSame(8 * 1000, $r['beneficios'][0]['valor'], 'o benefício começou no dia 6: 8 dias de 300 ÷ 30');
    }

    public function test_previa_de_pre_admissao_nao_tem_valores(): void
    {
        $r = app(PreviaDesligamento::class)->executar(Colaborador::factory()->create(), Carbon::parse('2026-11-10'));

        $this->assertSame([0, 0, []], [$r['remuneracao'], $r['total'], $r['beneficios']]);
        $this->assertStringContainsString('não foi admitido', $r['avisos'][0]);
    }

    public function test_desliga_registra_data_motivo_e_audita(): void
    {
        $c = $this->ativo();

        $resultado = $this->desligar($c, '2026-11-10', 'Fim da obra', obs: 'Acordo verbal');

        $c = $c->fresh();
        $this->assertSame(StatusColaborador::Desligado, $c->status);
        $this->assertSame('2026-11-10', $c->data_desligamento->toDateString());
        $this->assertSame('Fim da obra — Acordo verbal', $c->motivo_desligamento);
        $this->assertSame(100000, $resultado['previa']['remuneracao']);
        $this->assertNull($resultado['distrato']);
        $this->assertTrue(Activity::where('event', 'desligamento-registrado')->exists());
    }

    public function test_encerra_remuneracao_beneficios_e_equipes_no_dia_seguinte_e_apaga_o_agendado(): void
    {
        $c = $this->ativo();
        $b = Beneficio::factory()->create();
        $aberto = ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id, 'inicio' => '2026-01-05']);
        $futuro = ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => Beneficio::factory()->create()->id, 'inicio' => '2026-12-01']);
        $reajusteFuturo = Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 4000, 'vigencia_inicio' => '2026-12-01']);
        Remuneracao::where('colaborador_id', $c->id)->where('id', '!=', $reajusteFuturo->id)->update(['vigencia_fim' => '2026-12-01']);
        $equipe = ColaboradorEquipe::factory()->create(['colaborador_id' => $c->id, 'equipe_id' => Equipe::factory()->create()->id, 'inicio' => '2026-01-05']);

        $this->desligar($c, '2026-11-10');

        $this->assertSame('2026-11-11', Remuneracao::where('colaborador_id', $c->id)->vigentesEm('2026-11-10')->sole()->vigencia_fim->toDateString());
        $this->assertCount(0, Remuneracao::where('colaborador_id', $c->id)->vigentesEm('2026-11-11')->get(), 'não há remuneração no dia seguinte');
        $this->assertSoftDeleted($reajusteFuturo);
        $this->assertSame('2026-11-11', $aberto->fresh()->fim->toDateString());
        $this->assertSoftDeleted($futuro);
        $this->assertSame('2026-11-11', $equipe->fresh()->fim->toDateString());
    }

    public function test_nao_mexe_no_que_ja_estava_encerrado_antes(): void
    {
        $c = $this->ativo();
        $antigo = ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'inicio' => '2026-01-05', 'fim' => '2026-06-01']);

        $this->desligar($c, '2026-11-10');

        $this->assertSame('2026-06-01', $antigo->fresh()->fim->toDateString());
    }

    public function test_gera_o_distrato_encerra_o_contrato_e_cancela_os_rascunhos(): void
    {
        $this->modeloDistrato();
        $c = $this->ativo(comContrato: true);
        $contrato = $c->contratos()->where('tipo', 'contrato')->sole();
        $aditivo = Contrato::factory()->create(['colaborador_id' => $c->id, 'contrato_pai_id' => $contrato->id, 'tipo' => TipoContrato::Aditivo, 'motivo' => MotivoAditivo::Reajuste]);

        $resultado = $this->desligar($c, '2026-11-10', 'Fim da obra', distrato: true);

        $distrato = $resultado['distrato'];
        $this->assertSame($contrato->numero.'-D', $distrato->numero);
        $this->assertSame(StatusContrato::Gerado, $distrato->status);
        $this->assertSame($contrato->id, $distrato->contrato_pai_id);
        $this->assertStringContainsString('Distrato '.$distrato->numero.' de '.$c->pessoaFisica->nome.' em 10/11/2026: Fim da obra', $distrato->conteudo_renderizado);
        $this->assertSame(StatusContrato::Encerrado, $contrato->fresh()->status);
        $this->assertSoftDeleted($aditivo);
        $this->assertNotSoftDeleted($distrato);
        $this->assertNotNull($distrato->fresh()->pdf_path);
    }

    public function test_sem_contrato_desliga_e_avisa_que_nao_gerou_distrato(): void
    {
        $this->modeloDistrato();

        $resultado = $this->desligar($this->ativo(), distrato: true);

        $this->assertNull($resultado['distrato']);
        $this->assertStringContainsString('não tem contrato', $resultado['avisos'][0]);
    }

    public function test_distrato_pedido_sem_modelo_ativo_recusa_e_nada_muda(): void
    {
        $c = $this->ativo(comContrato: true);

        try {
            $this->desligar($c, distrato: true);
            $this->fail('Desligou sem modelo de distrato');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('modelo de distrato', $e->validator->errors()->first('modelo_distrato'));
        }

        $this->assertSame([StatusColaborador::Ativo, null], [$c->fresh()->status, $c->fresh()->data_desligamento]);
        $this->assertNull(Remuneracao::where('colaborador_id', $c->id)->value('vigencia_fim'));
        $this->assertSame(StatusContrato::Assinado, $c->contratos()->where('tipo', 'contrato')->sole()->status);
    }

    public function test_dado_faltando_no_distrato_desfaz_o_desligamento_inteiro(): void
    {
        $this->modeloDistrato('<p>{{contrato.numero}} {{cargo.nome}}</p>');
        $c = $this->ativo(comContrato: true);
        $beneficio = ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'inicio' => '2026-01-05']);

        try {
            $this->desligar($c, distrato: true);
            $this->fail('Desligou com dado faltando');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cargo.nome', $e->validator->errors()->first('contrato'));
        }

        $c = $c->fresh();
        $this->assertSame([StatusColaborador::Ativo, null, null], [$c->status, $c->data_desligamento, $c->motivo_desligamento]);
        $this->assertNull($beneficio->fresh()->fim);
        $this->assertSame(1, Contrato::where('colaborador_id', $c->id)->count(), 'o rascunho do distrato também foi desfeito');
    }

    public function test_regras_da_data_e_do_motivo(): void
    {
        $c = $this->ativo('3000', '2026-03-01');

        $casos = [
            'data futura' => fn () => $this->desligar($c, '2026-11-19'),
            'antes da admissão' => fn () => $this->desligar($c, '2026-02-28'),
            'motivo vazio' => fn () => $this->desligar($c, '2026-11-10', '   '),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Aceitou: {$nome}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(StatusColaborador::Ativo, $c->fresh()->status);
        $this->assertSame(StatusColaborador::Desligado, $this->desligar($c, '2026-11-18')['colaborador']->status, 'hoje vale');
    }

    public function test_hoje_e_aceito_e_so_se_desliga_uma_vez(): void
    {
        $c = $this->ativo();

        $this->assertSame(StatusColaborador::Desligado, $this->desligar($c, '2026-11-18')['colaborador']->status);

        $this->expectException(ValidationException::class);
        $this->desligar($c, '2026-11-18');
    }

    public function test_competencia_fechada_recusa(): void
    {
        $this->app->instance(CompetenciaFechada::class, new class implements CompetenciaFechada
        {
            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === '2026-10';
            }
        });
        $c = $this->ativo();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('fechada');
        $this->desligar($c, '2026-10-20');
    }

    public function test_desligado_nao_recebe_remuneracao_beneficio_nem_contrato(): void
    {
        $this->modeloDistrato();
        $c = $this->ativo(comContrato: true);
        $this->desligar($c, '2026-11-10');
        $c = $c->fresh();
        $contrato = ContratoModelo::factory()->create(['tipo' => TipoContrato::Contrato]);
        $aditivo = ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'nome' => 'Aditivo']);

        $bloqueios = [
            'reajuste' => fn () => app(ReajustarRemuneracao::class)->executar($c, FormaReajuste::Percentual, '10', Carbon::parse('2026-11-12')),
            'benefício' => fn () => app(VincularBeneficio::class)->executar($c, Beneficio::factory()->create(), Carbon::parse('2026-11-09')),
            'contrato novo' => fn () => app(CriarContrato::class)->executar($c, $contrato, Carbon::parse('2026-11-12')),
            'aditivo' => fn () => app(CriarContrato::class)->executar($c, $aditivo, Carbon::parse('2026-11-12'), null, $c->contratos()->where('tipo', 'contrato')->first()),
        ];

        foreach ($bloqueios as $nome => $acao) {
            try {
                $acao();
                $this->fail("Aceitou: {$nome}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_desligado_nao_entra_no_reajuste_em_lote_nem_gera_aditivo_automatico(): void
    {
        $c = $this->ativo();
        $this->desligar($c, '2026-11-10');

        $criar = app(\App\Actions\Contratos\CriarRascunhoDeAditivo::class);
        $this->assertSame('desligado', $criar->executar($c->fresh(), MotivoAditivo::Reajuste, Carbon::parse('2026-11-12'))['motivo']);

        $this->expectException(ValidationException::class);
        app(\App\Actions\Remuneracoes\PreverReajusteLote::class)->executar(['tipo' => 'lista', 'colaborador_ids' => [$c->id]], FormaReajuste::Valor, '100', Carbon::parse('2026-12-01'));
    }

    public function test_desligar_quem_esta_em_pre_admissao_cancela_a_admissao_e_libera_a_pessoa(): void
    {
        $c = Colaborador::factory()->create(['status' => StatusColaborador::PreAdmissao]);
        $this->desligar($c, '2026-11-10', 'Desistiu');

        $this->assertSame(StatusColaborador::Desligado, $c->fresh()->status);

        $novo = app(SalvarColaborador::class)->executar(null, ['pessoa_fisica_id' => $c->pessoa_fisica_id, 'pessoa_juridica_id' => $c->pessoa_juridica_id]);
        $this->assertSame(StatusColaborador::PreAdmissao, $novo->status);
    }

    public function test_fluxo_pela_tela(): void
    {
        $this->modeloDistrato();
        $c = $this->ativo(comContrato: true);
        $this->actingAs($this->usuarioCom('colaborador-list', 'colaborador-desligar'));

        $this->get(route('colaborador.show', $c->id))->assertOk()->assertSee('Desligar colaborador');
        $this->get(route('desligamento.form', $c->id))->assertOk()->assertSee('Último dia de trabalho')->assertSee('Gerar o distrato');

        $previa = $this->getJson(route('desligamento.previa', [$c->id, 'data' => '2026-11-10']))->assertOk()->json('data');
        $this->assertSame(['R$ 1.000,00', 'R$ 1.000,00'], [$previa['remuneracao'], $previa['total']]);
        $this->assertSame('01/11/2026 a 10/11/2026 (10 dias)', $previa['periodo']);
        $this->assertContains(PreviaDesligamento::AVISO, $previa['avisos']);
        $this->getJson(route('desligamento.previa', [$c->id, 'data' => 'x']))->assertStatus(422);

        $this->postJson(route('desligamento.store', $c->id), ['data' => '2026-11-10', 'motivo' => ''])->assertStatus(422);
        $this->postJson(route('desligamento.store', $c->id), ['data' => '2026-11-10', 'motivo' => 'Fim da obra', 'gerar_distrato' => 1])
            ->assertOk()->assertJsonPath('success', true)->assertSee('Distrato');

        $this->assertSame(StatusColaborador::Desligado, $c->fresh()->status);
        $this->get(route('colaborador.show', $c->id))->assertOk()->assertSee('Último dia de trabalho: 10/11/2026')->assertDontSee('Desligar colaborador');
        $this->get(route('desligamento.form', $c->id))->assertNotFound();
        $this->postJson(route('desligamento.store', $c->id), ['data' => '2026-11-10', 'motivo' => 'x'])->assertStatus(422);
    }

    public function test_so_quem_pode_desligar_ve_o_botao_e_gestor_nao_desliga_fora_da_equipe(): void
    {
        $c = $this->ativo();

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('colaborador.show', $c->id))->assertOk()->assertDontSee('Desligar colaborador');

        $this->actingAs($this->usuarioCom('colaborador-desligar', 'equipe-gerir'));
        $this->get(route('desligamento.form', $c->id))->assertNotFound();
        $this->getJson(route('desligamento.previa', [$c->id, 'data' => '2026-11-10']))->assertNotFound();
        $this->postJson(route('desligamento.store', $c->id), ['data' => '2026-11-10', 'motivo' => 'x'])->assertNotFound();
        $this->assertSame(StatusColaborador::Ativo, $c->fresh()->status);
    }

    public function test_pessoa_pode_voltar_a_ser_colaboradora_depois_de_desligada_e_o_historico_fica(): void
    {
        $c = $this->ativo(comContrato: true);
        $this->desligar($c, '2026-11-10');

        $novo = Colaborador::factory()->create(['pessoa_fisica_id' => $c->pessoa_fisica_id, 'status' => StatusColaborador::PreAdmissao]);

        $this->assertSame(2, Colaborador::where('pessoa_fisica_id', $c->pessoa_fisica_id)->count());
        $this->assertSame(1, Contrato::where('colaborador_id', $c->id)->count(), 'o contrato antigo continua no histórico');
        $this->assertSame(0, Contrato::where('colaborador_id', $novo->id)->count());
        $this->assertInstanceOf(PessoaFisica::class, $c->fresh()->pessoaFisica()->first());
    }
}

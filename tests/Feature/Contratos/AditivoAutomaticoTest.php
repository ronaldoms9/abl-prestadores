<?php

namespace Tests\Feature\Contratos;

use App\Actions\Beneficios\EncerrarBeneficioColaborador;
use App\Actions\Cadastros\SalvarColaborador;
use App\Actions\Contratos\CriarRascunhoDeAditivo;
use App\Actions\Contratos\GerarAditivosEmMassa;
use App\Actions\Remuneracoes\AplicarReajusteLote;
use App\Actions\Remuneracoes\PreverReajusteLote;
use App\Actions\Remuneracoes\ReajustarRemuneracao;
use App\Enums\FormaReajuste;
use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\Remuneracao;
use App\Support\Contratos\GeradorPdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AditivoAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->app->bind(GeradorPdf::class, fn () => new class implements GeradorPdf
        {
            public function gerar(string $html): string
            {
                return '%PDF';
            }
        });
    }

    private function colaboradorComContrato(?Cargo $cargo = null, StatusColaborador $status = StatusColaborador::Ativo, bool $comContrato = true): Colaborador
    {
        $colaborador = Colaborador::factory()->create(['status' => $status, 'cargo_id' => ($cargo ?? Cargo::factory()->create())->id, 'data_admissao' => '2026-01-05']);
        Remuneracao::factory()->create(['colaborador_id' => $colaborador->id, 'valor_mensal' => 5000, 'vigencia_inicio' => '2026-01-05']);

        if ($comContrato) {
            Contrato::factory()->gerado('2026/'.str_pad((string) (Contrato::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT))->create([
                'colaborador_id' => $colaborador->id, 'status' => StatusContrato::Assinado, 'vigencia_inicio' => '2026-01-05',
            ]);
        }

        return $colaborador;
    }

    private function modeloAditivo(?MotivoAditivo $motivo, string $corpo = '<p>{{contrato.numero}} sobre {{contrato.numero_pai}}: {{aditivo.motivo}}</p>'): ContratoModelo
    {
        return ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'motivo' => $motivo, 'nome' => 'Aditivo '.($motivo?->value ?? 'genérico'), 'corpo_html' => $corpo]);
    }

    private function aditivos(): \Illuminate\Database\Eloquent\Collection
    {
        return Contrato::where('tipo', TipoContrato::Aditivo->value)->orderBy('id')->get();
    }

    public function test_reajuste_individual_cria_o_rascunho_com_valores_antigo_e_novo(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste);
        $colaborador = $this->colaboradorComContrato();

        $reajuste = app(ReajustarRemuneracao::class)->executar($colaborador, FormaReajuste::Percentual, '10', Carbon::parse('2026-07-01'));

        $aditivo = $this->aditivos()->sole();
        $this->assertSame([MotivoAditivo::Reajuste, StatusContrato::Rascunho], [$aditivo->motivo, $aditivo->status]);
        $this->assertSame('2026-07-01', $aditivo->vigencia_inicio->toDateString());
        $this->assertSame($colaborador->contratos()->where('tipo', 'contrato')->value('id'), $aditivo->contrato_pai_id);
        $this->assertSame([500000, 550000, $reajuste->id], [$aditivo->dados['valor_anterior'], $aditivo->dados['valor_novo'], $aditivo->dados['reajuste_id']]);
        $this->assertNull($aditivo->numero);
    }

    public function test_reajuste_em_lote_cria_um_aditivo_por_colaborador_com_contrato(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste);
        $cargo = Cargo::factory()->create();
        $a = $this->colaboradorComContrato($cargo);
        $b = $this->colaboradorComContrato($cargo);
        $semContrato = $this->colaboradorComContrato($cargo, comContrato: false);

        $criterio = ['tipo' => 'cargo', 'cargo_id' => $cargo->id];
        $vigencia = Carbon::parse('2026-07-01');
        $hash = app(PreverReajusteLote::class)->executar($criterio, FormaReajuste::Valor, '300', $vigencia)['hash'];
        app(AplicarReajusteLote::class)->executar($criterio, FormaReajuste::Valor, '300', $vigencia, $hash, $this->usuarioCom());

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $this->aditivos()->pluck('colaborador_id')->all());
        $this->assertNotContains($semContrato->id, $this->aditivos()->pluck('colaborador_id')->all());
        $this->assertSame([530000, 530000], $this->aditivos()->map(fn ($c) => $c->dados['valor_novo'])->all());
    }

    public function test_sem_modelo_de_aditivo_o_reajuste_segue_sem_erro_e_sem_rascunho(): void
    {
        $colaborador = $this->colaboradorComContrato();

        app(ReajustarRemuneracao::class)->executar($colaborador, FormaReajuste::Percentual, '10', Carbon::parse('2026-07-01'));

        $this->assertCount(0, $this->aditivos());
        $this->assertSame('5500.00', Remuneracao::where('colaborador_id', $colaborador->id)->vigentes()->value('valor_mensal'));
    }

    public function test_usa_o_modelo_generico_quando_nao_ha_um_especifico(): void
    {
        $generico = $this->modeloAditivo(null);
        $colaborador = $this->colaboradorComContrato();

        app(ReajustarRemuneracao::class)->executar($colaborador, FormaReajuste::Percentual, '10', Carbon::parse('2026-07-01'));

        $this->assertSame($generico->id, $this->aditivos()->sole()->contrato_modelo_id);
    }

    public function test_a_mesma_alteracao_nao_gera_dois_rascunhos(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste);
        $colaborador = $this->colaboradorComContrato();
        $criar = app(CriarRascunhoDeAditivo::class);

        $primeiro = $criar->executar($colaborador, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), ['valor_novo' => 1], 'reajuste:9');
        $segundo = $criar->executar($colaborador, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), ['valor_novo' => 1], 'reajuste:9');

        $this->assertNotNull($primeiro['rascunho']);
        $this->assertSame([null, CriarRascunhoDeAditivo::DUPLICADO], [$segundo['rascunho'], $segundo['motivo']]);
        $this->assertCount(1, $this->aditivos());
    }

    public function test_explica_por_que_nao_criou(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste);
        $criar = app(CriarRascunhoDeAditivo::class);
        $inicio = Carbon::parse('2026-07-01');

        $this->assertSame(CriarRascunhoDeAditivo::SEM_CONTRATO, $criar->executar($this->colaboradorComContrato(comContrato: false), MotivoAditivo::Reajuste, $inicio)['motivo']);
        $this->assertSame(CriarRascunhoDeAditivo::DESLIGADO, $criar->executar($this->colaboradorComContrato(status: StatusColaborador::Desligado), MotivoAditivo::Reajuste, $inicio)['motivo']);
        $this->assertSame(CriarRascunhoDeAditivo::SEM_MODELO, $criar->executar($this->colaboradorComContrato(), MotivoAditivo::MudancaCargo, $inicio)['motivo']);
    }

    public function test_mudanca_de_cargo_de_quem_esta_ativo_cria_aditivo_com_os_nomes(): void
    {
        $this->modeloAditivo(MotivoAditivo::MudancaCargo, '<p>De {{aditivo.cargo_anterior}} para {{aditivo.cargo_novo}}</p>');
        $antigo = Cargo::factory()->create(['nome' => 'Pedreiro']);
        $novo = Cargo::factory()->create(['nome' => 'Mestre de obras']);
        $colaborador = $this->colaboradorComContrato($antigo);

        app(SalvarColaborador::class)->executar($colaborador, ['pessoa_juridica_id' => $colaborador->pessoa_juridica_id, 'cargo_id' => $novo->id]);

        $aditivo = $this->aditivos()->sole();
        $this->assertSame([MotivoAditivo::MudancaCargo, 'Pedreiro', 'Mestre de obras'], [$aditivo->motivo, $aditivo->dados['cargo_anterior'], $aditivo->dados['cargo_novo']]);
        $this->assertStringContainsString('De Pedreiro para Mestre de obras', app(\App\Actions\Contratos\GerarContrato::class)->executar($aditivo)->conteudo_renderizado);
    }

    public function test_cargo_igual_ou_colaborador_em_pre_admissao_nao_gera_aditivo(): void
    {
        $this->modeloAditivo(MotivoAditivo::MudancaCargo);
        $cargo = Cargo::factory()->create();
        $ativo = $this->colaboradorComContrato($cargo);
        $preAdmissao = $this->colaboradorComContrato($cargo, StatusColaborador::PreAdmissao);

        app(SalvarColaborador::class)->executar($ativo, ['pessoa_juridica_id' => $ativo->pessoa_juridica_id, 'cargo_id' => $cargo->id]);
        app(SalvarColaborador::class)->executar($preAdmissao, ['pessoa_juridica_id' => $preAdmissao->pessoa_juridica_id, 'cargo_id' => Cargo::factory()->create()->id]);

        $this->assertCount(0, $this->aditivos());
    }

    public function test_beneficio_incluido_ou_encerrado_pela_tela_cria_aditivo(): void
    {
        $this->modeloAditivo(MotivoAditivo::Beneficios, '<p>{{aditivo.beneficios_alteracao}}</p>');
        $colaborador = $this->colaboradorComContrato();
        $beneficio = Beneficio::factory()->create(['nome' => 'Vale transporte']);
        $this->actingAs($this->usuarioCom('colaborador-beneficio-edit'));

        $this->postJson(route('colaborador.beneficio.store', $colaborador->id), ['beneficio_id' => $beneficio->id, 'inicio' => '2026-07-01'])->assertOk();
        $vinculo = $colaborador->beneficios()->firstOrFail();
        $this->postJson(route('colaborador.beneficio.encerrar', $vinculo->id), ['fim' => '2026-09-01'])->assertOk();

        $this->assertSame(['Benefício incluído: Vale transporte', 'Benefício encerrado: Vale transporte'], $this->aditivos()->map(fn ($c) => $c->dados['beneficios_alteracao'])->all());
    }

    public function test_modelo_generico_com_detalhe_gera_para_qualquer_motivo(): void
    {
        $this->modeloAditivo(null, '<p>{{aditivo.motivo}}: {{aditivo.detalhe}}</p>');
        $colaborador = $this->colaboradorComContrato();
        $beneficio = Beneficio::factory()->create(['nome' => 'Vale transporte']);
        $this->actingAs($this->usuarioCom('colaborador-beneficio-edit'));
        $this->postJson(route('colaborador.beneficio.store', $colaborador->id), ['beneficio_id' => $beneficio->id, 'inicio' => '2026-07-01'])->assertOk();

        $aditivo = $this->aditivos()->firstOrFail();
        $gerado = app(\App\Actions\Contratos\GerarContrato::class)->executar($aditivo, \Carbon\Carbon::parse('2026-07-01'));

        $this->assertStringContainsString('Benefício incluído: Vale transporte.', $gerado->conteudo_renderizado);
    }

    public function test_beneficios_padrao_da_admissao_nao_geram_aditivo(): void
    {
        $this->modeloAditivo(MotivoAditivo::Beneficios);
        $colaborador = $this->colaboradorComContrato();
        $cargo = Cargo::find($colaborador->cargo_id);
        \App\Models\CargoBeneficio::factory()->create(['cargo_id' => $cargo->id, 'beneficio_id' => Beneficio::factory()->create()->id]);

        app(\App\Actions\Beneficios\AplicarBeneficiosPadrao::class)->executar($colaborador, Carbon::parse('2026-07-01'));

        $this->assertCount(0, $this->aditivos());
    }

    public function test_gera_em_massa_e_os_que_falham_nao_barram_os_outros(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste, '<p>{{aditivo.valor_novo}} {{contrato.numero}}</p>');
        $ok = $this->colaboradorComContrato();
        $semDado = $this->colaboradorComContrato();
        $criar = app(CriarRascunhoDeAditivo::class);
        $a = $criar->executar($ok, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), ['valor_novo' => 550000], 'x:1')['rascunho'];
        $b = $criar->executar($semDado, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), [], 'x:2')['rascunho'];

        $resultado = app(GerarAditivosEmMassa::class)->executar(Contrato::with('colaborador.pessoaFisica')->whereIn('id', [$a->id, $b->id])->get());

        $this->assertSame(1, $resultado['gerados']);
        $this->assertCount(1, $resultado['falhas']);
        $this->assertSame($b->id, $resultado['falhas'][0]['id']);
        $this->assertStringContainsString('aditivo.valor_novo', $resultado['falhas'][0]['mensagem']);
        $this->assertSame(StatusContrato::Gerado, $a->fresh()->status);
        $this->assertSame(StatusContrato::Rascunho, $b->fresh()->status);
    }

    public function test_tela_lista_os_pendentes_e_gera_os_selecionados(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste, '<p>{{aditivo.valor_novo}}</p>');
        $colaborador = $this->colaboradorComContrato();
        $rascunho = app(CriarRascunhoDeAditivo::class)->executar($colaborador, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), ['valor_anterior' => 500000, 'valor_novo' => 550000], 'r:1')['rascunho'];
        $this->actingAs($this->usuarioCom('contrato-gerar'));

        $this->get(route('aditivo.pendentes'))->assertOk()->assertSee($colaborador->pessoaFisica->nome)->assertSee('R$ 5.000,00 → R$ 5.500,00');

        $this->postJson(route('aditivo.pendentes.gerar'), ['ids' => [$rascunho->id]])->assertOk()->assertJsonPath('data.gerados', 1);
        $this->assertSame(StatusContrato::Gerado, $rascunho->fresh()->status);
        $this->get(route('aditivo.pendentes'))->assertOk()->assertSee('Nenhum aditivo pendente');
        $this->postJson(route('aditivo.pendentes.gerar'), ['ids' => []])->assertStatus(422);
    }

    public function test_gestor_so_ve_pendentes_dos_colaboradores_que_pode_ver(): void
    {
        $this->modeloAditivo(MotivoAditivo::Reajuste);
        $colaborador = $this->colaboradorComContrato();
        $rascunho = app(CriarRascunhoDeAditivo::class)->executar($colaborador, MotivoAditivo::Reajuste, Carbon::parse('2026-07-01'), ['valor_novo' => 1], 'r:1')['rascunho'];

        $this->actingAs($this->usuarioCom('contrato-gerar', 'equipe-gerir'))
            ->get(route('aditivo.pendentes'))->assertOk()->assertDontSee($colaborador->pessoaFisica->nome);
        $this->postJson(route('aditivo.pendentes.gerar'), ['ids' => [$rascunho->id]])->assertOk()->assertJsonPath('data.gerados', 0);
        $this->assertSame(StatusContrato::Rascunho, $rascunho->fresh()->status);
    }
}

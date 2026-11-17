<?php

namespace Tests\Feature\Contratos;

use App\Actions\Contratos\AnexarContratoAssinado;
use App\Actions\Contratos\CancelarRascunho;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Configuracao;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\Remuneracao;
use App\Models\ResponsavelPessoaJuridica;
use App\Support\Contratos\GeradorPdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ContratoTest extends TestCase
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
                return '%PDF-teste '.strlen($html);
            }
        });
    }

    private function colaborador(array $extra = [], bool $comRemuneracao = true): Colaborador
    {
        $pf = PessoaFisica::factory()->create(['nome' => 'Maria da Silva']);
        $pj = PessoaJuridica::factory()->create(['razao_social' => 'Silva Serviços LTDA']);
        $colaborador = Colaborador::factory()->create($extra + [
            'pessoa_fisica_id' => $pf->id,
            'pessoa_juridica_id' => $pj->id,
            'cargo_id' => Cargo::firstOrCreate(['nome' => 'Mestre de obras'], ['faixa_min' => 3000, 'faixa_max' => 6000, 'ativo' => true])->id,
            'status' => StatusColaborador::Ativo,
            'data_admissao' => '2026-03-02',
        ]);

        if ($comRemuneracao) {
            Remuneracao::factory()->create(['colaborador_id' => $colaborador->id, 'valor_mensal' => 5250, 'vigencia_inicio' => '2026-01-01']);
        }

        return $colaborador;
    }

    private function modelo(TipoContrato $tipo = TipoContrato::Contrato, ?string $corpo = null): ContratoModelo
    {
        return ContratoModelo::factory()->create([
            'tipo' => $tipo,
            'nome' => $tipo->rotulo().' padrão',
            'corpo_html' => $corpo ?? '<p>{{contrato.numero}}: {{colaborador.nome}} ({{pj.razao_social}}), {{cargo.nome}}, {{remuneracao.valor}} ({{remuneracao.valor_extenso}}).</p>',
        ]);
    }

    private function rascunho(Colaborador $colaborador, ?ContratoModelo $modelo = null): Contrato
    {
        return app(CriarContrato::class)->executar($colaborador, $modelo ?? $this->modelo(), Carbon::parse('2026-03-02'), Carbon::parse('2027-03-02'));
    }

    private function gerado(Colaborador $colaborador, ?Carbon $hoje = null): Contrato
    {
        return app(GerarContrato::class)->executar($this->rascunho($colaborador), $hoje ?? Carbon::parse('2026-03-02'));
    }

    public function test_gera_o_contrato_preenchendo_o_modelo_numerando_e_auditando(): void
    {
        $contrato = $this->gerado($this->colaborador());

        $this->assertSame('2026/0001', $contrato->numero);
        $this->assertSame([2026, 1], [$contrato->ano, $contrato->sequencia]);
        $this->assertSame(StatusContrato::Gerado, $contrato->status);
        $this->assertNotNull($contrato->gerado_em);
        $this->assertSame(
            '<p>2026/0001: Maria da Silva (Silva Serviços LTDA), Mestre de obras, R$ 5.250,00 (cinco mil duzentos e cinquenta reais).</p>',
            $contrato->conteudo_renderizado,
        );
        $this->assertTrue(Activity::where('event', 'contrato-gerado')->exists());
    }

    public function test_numeros_seguem_em_ordem_e_reiniciam_no_ano_novo(): void
    {
        $a = $this->gerado($this->colaborador());
        $b = $this->gerado($this->colaborador());
        $c = $this->gerado($this->colaborador(), Carbon::parse('2027-01-05'));

        $this->assertSame(['2026/0001', '2026/0002', '2027/0001'], [$a->numero, $b->numero, $c->numero]);
    }

    public function test_prefixo_e_sem_reinicio_anual_sao_configuraveis(): void
    {
        Configuracao::definir('contratos.numeracao', ['prefixo' => 'ABL-', 'reinicia_anualmente' => false]);

        $a = $this->gerado($this->colaborador());
        $b = $this->gerado($this->colaborador(), Carbon::parse('2027-01-05'));

        $this->assertSame(['ABL-2026/0001', 'ABL-2027/0002'], [$a->numero, $b->numero]);
    }

    public function test_cancelar_rascunho_nao_consome_numero_e_numero_cancelado_nao_se_repete(): void
    {
        $colaborador = $this->colaborador();
        $cancelado = $this->rascunho($colaborador);
        app(CancelarRascunho::class)->executar($cancelado);
        $this->assertSoftDeleted($cancelado);

        $this->assertSame('2026/0001', $this->gerado($colaborador)->numero);
    }

    public function test_dado_faltando_recusa_a_geracao_diz_quais_e_nao_consome_numero(): void
    {
        $semRemuneracao = $this->colaborador([], false);
        $rascunho = $this->rascunho($semRemuneracao);

        try {
            app(GerarContrato::class)->executar($rascunho, Carbon::parse('2026-03-02'));
            $this->fail('Gerou com dado faltando');
        } catch (ValidationException $e) {
            $mensagem = $e->validator->errors()->first('contrato');
            $this->assertStringContainsString('{{remuneracao.valor}}', $mensagem);
            $this->assertStringContainsString('{{remuneracao.valor_extenso}}', $mensagem);
        }

        $this->assertSame(StatusContrato::Rascunho, $rascunho->fresh()->status);
        $this->assertNull($rascunho->fresh()->numero);
        $this->assertSame('2026/0001', $this->gerado($this->colaborador())->numero);
    }

    public function test_previa_mostra_o_que_falta_sem_gravar(): void
    {
        $rascunho = $this->rascunho($this->colaborador([], false));

        $resultado = app(GerarContrato::class)->previa($rascunho);

        $this->assertFalse($resultado->completo());
        $this->assertContains('remuneracao.valor', $resultado->faltantes);
        $this->assertNull($rascunho->fresh()->numero);
        $this->assertSame(StatusContrato::Rascunho, $rascunho->fresh()->status);
    }

    public function test_contrato_gerado_e_imutavel_mas_aceita_arquivos_e_status(): void
    {
        $contrato = $this->gerado($this->colaborador());

        foreach (['conteudo_renderizado' => '<p>outro</p>', 'numero' => '2026/9999', 'vigencia_fim' => '2030-01-01', 'colaborador_id' => 999] as $campo => $valor) {
            try {
                $contrato->fresh()->update([$campo => $valor]);
                $this->fail("Alterou {$campo} de contrato gerado");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $contrato->fresh()->update(['pdf_path' => 'x.pdf']);
        $this->assertSame('x.pdf', $contrato->fresh()->pdf_path);

        $this->expectException(LogicException::class);
        $contrato->fresh()->delete();
    }

    public function test_so_rascunho_e_cancelado(): void
    {
        $this->expectException(ValidationException::class);
        app(CancelarRascunho::class)->executar($this->gerado($this->colaborador()));
    }

    public function test_nao_gera_duas_vezes_o_mesmo_rascunho(): void
    {
        $contrato = $this->gerado($this->colaborador());

        $this->expectException(ValidationException::class);
        app(GerarContrato::class)->executar($contrato);
    }

    public function test_aditivo_herda_o_numero_do_pai_e_conta_de_um_em_um(): void
    {
        $colaborador = $this->colaborador();
        $pai = $this->gerado($colaborador);
        $modelo = $this->modelo(TipoContrato::Aditivo, '<p>Aditivo {{contrato.numero}} ao {{contrato.numero_pai}}: {{aditivo.motivo}}, de {{aditivo.valor_anterior}} para {{aditivo.valor_novo}} ({{aditivo.valor_novo_extenso}}).</p>');

        $dados = ['valor_anterior' => 500000, 'valor_novo' => 525000];
        $a1 = app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2026-06-01'), null, $pai, MotivoAditivo::Reajuste, $dados);
        $a2 = app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2026-09-01'), null, $pai, MotivoAditivo::Outro, $dados);

        $g1 = app(GerarContrato::class)->executar($a1, Carbon::parse('2026-06-01'));
        $g2 = app(GerarContrato::class)->executar($a2, Carbon::parse('2026-09-01'));

        $this->assertSame(['2026/0001-A01', '2026/0001-A02'], [$g1->numero, $g2->numero]);
        $this->assertSame($pai->id, $g1->contrato_pai_id);
        $this->assertSame('<p>Aditivo 2026/0001-A01 ao 2026/0001: Reajuste de remuneração, de R$ 5.000,00 para R$ 5.250,00 (cinco mil duzentos e cinquenta reais).</p>', $g1->conteudo_renderizado);
    }

    public function test_distrato_leva_sufixo_d_e_so_um_por_contrato(): void
    {
        $colaborador = $this->colaborador();
        $pai = $this->gerado($colaborador);
        $modelo = $this->modelo(TipoContrato::Distrato, '<p>Distrato {{contrato.numero}} do {{contrato.numero_pai}}</p>');

        $distrato = app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2026-12-01'), null, $pai);
        $gerado = app(GerarContrato::class)->executar($distrato);

        $this->assertSame('2026/0001-D', $gerado->numero);
        $this->assertSame('<p>Distrato 2026/0001-D do 2026/0001</p>', $gerado->conteudo_renderizado);

        $this->expectException(ValidationException::class);
        app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2026-12-02'), null, $pai);
    }

    public function test_regras_de_criacao(): void
    {
        $colaborador = $this->colaborador();
        $contrato = $this->modelo();
        $aditivo = $this->modelo(TipoContrato::Aditivo, '<p>{{contrato.numero}}</p>');
        $criar = app(CriarContrato::class);
        $inicio = Carbon::parse('2026-03-02');

        $rascunho = $criar->executar($colaborador, $contrato, $inicio);

        $casos = [
            'segundo rascunho' => fn () => $criar->executar($colaborador, $contrato, $inicio),
            'aditivo sem pai' => fn () => $criar->executar($colaborador, $aditivo, $inicio),
            'aditivo com pai ainda rascunho' => fn () => $criar->executar($colaborador, $aditivo, $inicio, null, $rascunho),
            'contrato sem início' => fn () => $criar->executar($this->colaborador(), $contrato, null),
            'fim antes do início' => fn () => $criar->executar($this->colaborador(), $contrato, $inicio, Carbon::parse('2026-01-01')),
            'modelo inativo' => fn () => $criar->executar($this->colaborador(), tap($this->modelo(), fn ($m) => $m->update(['ativo' => false])), $inicio),
            'desligado' => fn () => $criar->executar($this->colaborador(['status' => StatusColaborador::Desligado]), $contrato, $inicio),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Aceitou: {$nome}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_contrato_novo_exige_que_nao_haja_outro_em_vigor_e_aditivo_so_do_mesmo_colaborador(): void
    {
        $colaborador = $this->colaborador();
        $this->gerado($colaborador);
        $criar = app(CriarContrato::class);

        try {
            $criar->executar($colaborador, $this->modelo(), Carbon::parse('2026-06-01'));
            $this->fail('Aceitou segundo contrato em vigor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('aditivo', $e->validator->errors()->first('colaborador'));
        }

        $outro = $this->colaborador();
        $paiDeOutro = $this->gerado($outro);
        $this->expectException(ValidationException::class);
        $criar->executar($colaborador, $this->modelo(TipoContrato::Aditivo, '<p>x</p>'), Carbon::parse('2026-06-01'), null, $paiDeOutro);
    }

    public function test_contrato_encerrado_libera_um_novo(): void
    {
        $colaborador = $this->colaborador();
        $primeiro = $this->gerado($colaborador);
        $primeiro->update(['status' => StatusContrato::Encerrado]);

        $this->assertSame('2026/0002', $this->gerado($colaborador)->numero);
    }

    public function test_o_pdf_e_gerado_e_guardado_no_disco_privado(): void
    {
        $contrato = $this->gerado($this->colaborador())->fresh();

        $this->assertNotNull($contrato->pdf_path);
        $this->assertStringStartsWith('contratos/2026/'.$contrato->id.'/2026-0001-', $contrato->pdf_path);
        Storage::disk('local')->assertExists($contrato->pdf_path);
        $this->assertStringStartsWith('%PDF-teste', Storage::disk('local')->get($contrato->pdf_path));
    }

    public function test_sem_a_biblioteca_de_pdf_o_contrato_sai_mesmo_assim_e_o_pdf_fica_pendente(): void
    {
        $this->app->bind(GeradorPdf::class, \App\Support\Contratos\PdfIndisponivel::class);

        $contrato = $this->gerado($this->colaborador())->fresh();

        $this->assertSame(StatusContrato::Gerado, $contrato->status);
        $this->assertNull($contrato->pdf_path);
    }

    public function test_anexa_o_assinado_uma_vez_so_e_so_em_contrato_gerado(): void
    {
        $anexar = app(AnexarContratoAssinado::class);

        try {
            $anexar->executar($this->rascunho($this->colaborador()), UploadedFile::fake()->create('assinado.pdf', 100, 'application/pdf'));
            $this->fail('Anexou em rascunho');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $contrato = $this->gerado($this->colaborador());
        $assinado = $anexar->executar($contrato, UploadedFile::fake()->create('assinado.pdf', 100, 'application/pdf'));

        $this->assertSame(StatusContrato::Assinado, $assinado->status);
        $this->assertNotNull($assinado->assinado_em);
        Storage::disk('local')->assertExists($assinado->assinado_path);
        $this->assertTrue(Activity::where('event', 'contrato-assinado')->exists());

        $this->expectException(ValidationException::class);
        $anexar->executar($assinado, UploadedFile::fake()->create('outro.pdf', 100, 'application/pdf'));
    }

    public function test_fluxo_pela_tela_cria_previsualiza_gera_baixa_e_anexa(): void
    {
        $colaborador = $this->colaborador();
        $modelo = $this->modelo();
        $this->actingAs($this->usuarioCom('colaborador-list', 'contrato-list', 'contrato-gerar', 'contrato-assinar'));

        $id = $this->postJson(route('contrato.store', $colaborador->id), ['contrato_modelo_id' => $modelo->id, 'vigencia_inicio' => '2026-03-02', 'vigencia_fim' => ''])
            ->assertOk()->json('data.id');

        $this->get(route('contrato.previa', $id))->assertOk()->assertSee('Maria da Silva')->assertSee('R$ 5.250,00');
        $this->postJson(route('contrato.gerar', $id))->assertOk()->assertJsonPath('success', true);

        $contrato = Contrato::findOrFail($id);
        $this->assertStringStartsWith('2026/', $contrato->numero);
        $this->get(route('contrato.pdf', $id))->assertOk()->assertHeader('content-disposition');
        $this->get(route('contrato.assinado.baixar', $id))->assertNotFound();

        $this->postJson(route('contrato.assinado', $id), ['arquivo' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')])->assertOk();
        $this->get(route('contrato.assinado.baixar', $id))->assertOk();
        $this->postJson(route('contrato.assinado', $id), ['arquivo' => UploadedFile::fake()->create('a.exe', 50)])->assertStatus(422);
        $this->deleteJson(route('contrato.cancelar', $id))->assertStatus(422);

        $this->get(route('colaborador.show', $colaborador->id))->assertOk()->assertSee($contrato->numero)->assertSee('Assinado');
    }

    public function test_erro_de_regra_volta_como_422_com_a_mensagem(): void
    {
        $colaborador = $this->colaborador();
        $this->actingAs($this->usuarioCom('contrato-gerar'));
        $dados = ['contrato_modelo_id' => $this->modelo()->id, 'vigencia_inicio' => '2026-03-02'];

        $this->postJson(route('contrato.store', $colaborador->id), $dados)->assertOk();
        $this->postJson(route('contrato.store', $colaborador->id), $dados)->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson(route('contrato.store', $colaborador->id), ['contrato_modelo_id' => 99999])->assertStatus(422);
    }

    public function test_gestor_nao_mexe_em_contrato_de_colaborador_de_outra_equipe(): void
    {
        $contrato = $this->gerado($this->colaborador());
        $gestor = $this->usuarioCom('contrato-list', 'contrato-gerar', 'contrato-assinar', 'equipe-gerir');

        $this->actingAs($gestor)->get(route('contrato.previa', $contrato->id))->assertNotFound();
        $this->get(route('contrato.pdf', $contrato->id))->assertNotFound();
        $this->postJson(route('contrato.pdf.regerar', $contrato->id))->assertNotFound();
        $this->postJson(route('contrato.store', $contrato->colaborador_id), ['contrato_modelo_id' => $this->modelo()->id, 'vigencia_inicio' => '2026-03-02'])->assertNotFound();
    }

    public function test_quem_nao_ve_contratos_nao_ve_a_secao(): void
    {
        $contrato = $this->gerado($this->colaborador());

        $this->actingAs($this->usuarioCom('colaborador-list'))
            ->get(route('colaborador.show', $contrato->colaborador_id))->assertOk()->assertDontSee($contrato->numero);
    }

    public function test_variaveis_de_responsavel_beneficios_e_datas(): void
    {
        $colaborador = $this->colaborador(['data_admissao' => '2026-03-02']);
        $responsavel = PessoaFisica::factory()->create(['nome' => 'João Responsável']);
        ResponsavelPessoaJuridica::factory()->create(['pessoa_juridica_id' => $colaborador->pessoa_juridica_id, 'pessoa_fisica_id' => $responsavel->id, 'inicio' => '2026-01-01', 'fim' => null]);
        $vt = Beneficio::factory()->create(['nome' => 'Vale <transporte>']);
        BeneficioValor::factory()->create(['beneficio_id' => $vt->id, 'valor' => 300, 'vigencia_inicio' => '2026-01-01']);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $colaborador->id, 'beneficio_id' => $vt->id, 'inicio' => '2026-03-02']);

        $modelo = $this->modelo(TipoContrato::Contrato, '<p>{{pj.responsavel_nome}}|{{colaborador.data_admissao}}|{{contrato.vigencia_inicio}}|{{contrato.vigencia_fim}}|{{data.hoje_extenso}}</p>{{beneficios.lista}}');
        $contrato = app(GerarContrato::class)->executar(
            app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2026-03-02'), Carbon::parse('2027-03-02')),
            Carbon::parse('2026-03-02'),
        );

        $this->assertStringContainsString('<p>João Responsável|02/03/2026|02/03/2026|02/03/2027|2 de março de 2026</p>', $contrato->conteudo_renderizado);
        $this->assertStringContainsString('<ul><li>Vale &lt;transporte&gt;: R$ 300,00 (fixo mensal)</li></ul>', $contrato->conteudo_renderizado);
    }
}

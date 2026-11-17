<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Qualificacoes\RegistrarQualificacao;
use App\Actions\Qualificacoes\SalvarMatrizQualificacoes;
use App\Enums\CategoriaQualificacao;
use App\Models\Cargo;
use App\Models\CargoQualificacao;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\ColaboradorQualificacao;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\PessoaFisica;
use App\Models\QualificacaoTipo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class QualificacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rh()
    {
        return $this->usuarioCom('qualificacao-list', 'qualificacao-edit', 'qualificacao-tipo-gerir', 'colaborador-list');
    }

    // --- tipos ---------------------------------------------------------------

    public function test_crud_dos_tipos_de_qualificacao(): void
    {
        $this->actingAs($this->rh())->postJson(route('qualificacao-tipo.store'), ['nome' => 'NR-35', 'categoria' => 'treinamento_seguranca', 'validade_meses' => '24'])->assertOk();
        $tipo = QualificacaoTipo::firstOrFail();
        $this->assertSame(24, $tipo->validade_meses);
        $this->assertSame(CategoriaQualificacao::TreinamentoSeguranca, $tipo->categoria);
        $this->assertTrue(Activity::where('event', 'qualificacao-tipo-criado')->exists());

        $this->postJson(route('qualificacao-tipo.store'), ['nome' => 'NR-35', 'categoria' => 'curso'])->assertStatus(422);
        $this->postJson(route('qualificacao-tipo.store'), ['nome' => 'X', 'categoria' => 'curso', 'validade_meses' => '0'])->assertStatus(422);
        $this->postJson(route('qualificacao-tipo.store', $tipo->id), ['nome' => 'NR-35', 'categoria' => 'curso', 'validade_meses' => '', 'ativo' => 0])->assertOk();
        $this->assertNull($tipo->fresh()->validade_meses, 'em branco: não vence');
        $this->assertFalse($tipo->fresh()->ativo);
    }

    public function test_tipo_com_registro_ou_na_matriz_nao_e_excluido(): void
    {
        $usado = QualificacaoTipo::factory()->create();
        ColaboradorQualificacao::factory()->create(['qualificacao_tipo_id' => $usado->id]);
        $naMatriz = QualificacaoTipo::factory()->create();
        CargoQualificacao::factory()->create(['qualificacao_tipo_id' => $naMatriz->id]);
        $livre = QualificacaoTipo::factory()->create();
        $rh = $this->actingAs($this->rh());

        $rh->deleteJson(route('qualificacao-tipo.delete', $usado->id))->assertStatus(400);
        $rh->deleteJson(route('qualificacao-tipo.delete', $naMatriz->id))->assertStatus(400);
        $rh->deleteJson(route('qualificacao-tipo.delete', $livre->id))->assertStatus(201);
    }

    // --- matriz --------------------------------------------------------------

    public function test_matriz_grava_obrigatoria_opcional_e_retira(): void
    {
        $cargo = Cargo::factory()->create();
        $a = QualificacaoTipo::factory()->create();
        $b = QualificacaoTipo::factory()->create();
        $c = QualificacaoTipo::factory()->create();
        $salvar = app(SalvarMatrizQualificacoes::class);

        $this->assertSame(2, $salvar->executar([$cargo->id => [$a->id => 'obrigatoria', $b->id => 'opcional', $c->id => 'nenhuma']]));

        $this->assertTrue((bool) CargoQualificacao::where(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $a->id])->value('obrigatoria'));
        $this->assertFalse((bool) CargoQualificacao::where(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $b->id])->value('obrigatoria'));
        $this->assertSame(2, CargoQualificacao::count());

        $this->assertSame(0, $salvar->executar([$cargo->id => [$a->id => 'obrigatoria', $b->id => 'opcional']]), 'sem mudança, sem gravar');
        $this->assertSame(2, $salvar->executar([$cargo->id => [$a->id => 'opcional', $b->id => 'nenhuma']]));
        $this->assertSame(1, CargoQualificacao::count());
        $this->assertTrue(Activity::where('event', 'matriz-qualificacoes-alterada')->exists());
    }

    public function test_matriz_reaproveita_a_linha_retirada_em_vez_de_duplicar(): void
    {
        $cargo = Cargo::factory()->create();
        $tipo = QualificacaoTipo::factory()->create();
        $salvar = app(SalvarMatrizQualificacoes::class);

        $salvar->executar([$cargo->id => [$tipo->id => 'obrigatoria']]);
        $salvar->executar([$cargo->id => [$tipo->id => 'nenhuma']]);
        $salvar->executar([$cargo->id => [$tipo->id => 'opcional']]);

        $this->assertSame(1, CargoQualificacao::withTrashed()->count());
        $this->assertFalse((bool) CargoQualificacao::firstOrFail()->obrigatoria);
    }

    public function test_matriz_ignora_cargo_e_tipo_inexistentes_e_estado_invalido_e_recusado(): void
    {
        $cargo = Cargo::factory()->create();
        $tipo = QualificacaoTipo::factory()->create();

        $this->assertSame(0, app(SalvarMatrizQualificacoes::class)->executar([999 => [$tipo->id => 'obrigatoria'], $cargo->id => [999 => 'obrigatoria']]));

        $this->actingAs($this->rh())->postJson(route('qualificacao.matriz.salvar'), ['celulas' => [$cargo->id => [$tipo->id => 'talvez']]])->assertStatus(422);
        $this->postJson(route('qualificacao.matriz.salvar'), ['celulas' => [$cargo->id => [$tipo->id => 'obrigatoria']]])->assertOk()->assertJsonPath('data.mudancas', 1);
    }

    public function test_tela_da_matriz_mostra_o_estado_atual(): void
    {
        $cargo = Cargo::factory()->create(['nome' => 'Eletricista']);
        $tipo = QualificacaoTipo::factory()->create(['nome' => 'NR-10']);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $tipo->id, 'obrigatoria' => true]);

        $this->actingAs($this->rh())->get(route('qualificacao.matriz'))->assertOk()->assertSee('Eletricista')->assertSee('NR-10')
            ->assertSee('value="obrigatoria" selected', false);
    }

    // --- registrar ------------------------------------------------------------

    public function test_validade_e_calculada_a_partir_da_conclusao(): void
    {
        $colaborador = Colaborador::factory()->create();
        $tipo = QualificacaoTipo::factory()->create(['validade_meses' => 12]);
        $semValidade = QualificacaoTipo::factory()->create(['validade_meses' => null]);
        $fimDeMes = QualificacaoTipo::factory()->create(['validade_meses' => 1]);
        $acao = app(RegistrarQualificacao::class);

        $this->assertSame('2027-01-15', $acao->executar($colaborador, $tipo, Carbon::parse('2026-01-15'))->data_validade->toDateString());
        $this->assertNull($acao->executar($colaborador, $semValidade, Carbon::parse('2026-01-15'))->data_validade);
        $this->assertSame('2026-02-28', $acao->executar($colaborador, $fimDeMes, Carbon::parse('2026-01-31'))->data_validade->toDateString(), 'sem estourar o fim do mês');
    }

    public function test_recusa_data_futura_e_tipo_inativo(): void
    {
        $colaborador = Colaborador::factory()->create();
        $acao = app(RegistrarQualificacao::class);

        foreach ([[QualificacaoTipo::factory()->create(), Carbon::parse('2026-11-19')], [QualificacaoTipo::factory()->create(['ativo' => false]), Carbon::parse('2026-11-01')]] as [$tipo, $data]) {
            try {
                $acao->executar($colaborador, $tipo, $data);
                $this->fail('Deveria recusar.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, ColaboradorQualificacao::count());
        $this->assertSame(1, $acao->executar($colaborador, QualificacaoTipo::factory()->create(), Carbon::parse('2026-11-18'))->colaborador_id === $colaborador->id ? 1 : 0, 'hoje vale');
    }

    public function test_registra_pela_tela_com_certificado_em_disco_privado_e_auditoria(): void
    {
        $colaborador = Colaborador::factory()->create();
        $tipo = QualificacaoTipo::factory()->create(['validade_meses' => 24]);

        $this->actingAs($this->rh())->post(route('qualificacao.registrar', $colaborador->id), [
            'qualificacao_tipo_id' => $tipo->id, 'data_conclusao' => '2026-05-10', 'instituicao' => 'SENAI',
            'certificado' => UploadedFile::fake()->create('nr35.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $q = ColaboradorQualificacao::firstOrFail();
        $this->assertSame('2028-05-10', $q->data_validade->toDateString());
        $this->assertSame('SENAI', $q->instituicao);
        $this->assertSame('nr35.pdf', $q->certificado_nome);
        $this->assertStringStartsWith("qualificacoes/{$tipo->id}/{$q->id}/", $q->certificado_path);
        Storage::disk('local')->assertExists($q->certificado_path);
        $json = Activity::where('event', 'qualificacao-registrada')->firstOrFail()->properties->toJson();
        $this->assertStringNotContainsString('nr35.pdf', $json);

        $this->get(route('qualificacao.certificado', $q->id))->assertOk()->assertDownload('nr35.pdf');
    }

    public function test_certificado_e_opcional_mas_o_arquivo_precisa_ser_pdf_ou_imagem(): void
    {
        $colaborador = Colaborador::factory()->create();
        $tipo = QualificacaoTipo::factory()->create();
        $rh = $this->actingAs($this->rh());
        $dados = ['qualificacao_tipo_id' => $tipo->id, 'data_conclusao' => '2026-05-10'];

        $rh->postJson(route('qualificacao.registrar', $colaborador->id), $dados)->assertOk();
        $rh->post(route('qualificacao.registrar', $colaborador->id), $dados + ['certificado' => UploadedFile::fake()->create('x.exe', 5, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(1, ColaboradorQualificacao::count());
    }

    public function test_exclui_a_qualificacao_e_audita(): void
    {
        $q = ColaboradorQualificacao::factory()->create();

        $this->actingAs($this->rh())->deleteJson(route('qualificacao.excluir', $q->id))->assertStatus(201);

        $this->assertSoftDeleted($q);
        $this->assertTrue(Activity::where('event', 'qualificacao-excluida')->exists());
    }

    public function test_gestor_so_mexe_nas_qualificacoes_de_colaborador_da_propria_equipe(): void
    {
        $gestor = $this->usuarioCom('qualificacao-list', 'qualificacao-edit', 'colaborador-list', 'equipe-gerir');
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => now()->subMonth()]);
        $meu = Colaborador::factory()->create();
        $alheio = Colaborador::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $equipe->id, 'inicio' => now()->subMonth()]);
        $tipo = QualificacaoTipo::factory()->create();
        $dados = ['qualificacao_tipo_id' => $tipo->id, 'data_conclusao' => '2026-05-10'];

        $this->actingAs($gestor)->postJson(route('qualificacao.registrar', $meu->id), $dados)->assertOk();
        $this->postJson(route('qualificacao.registrar', $alheio->id), $dados)->assertNotFound();

        $qAlheio = ColaboradorQualificacao::factory()->create(['colaborador_id' => $alheio->id]);
        $this->deleteJson(route('qualificacao.excluir', $qAlheio->id))->assertNotFound();
        $this->get(route('qualificacao.certificado', $qAlheio->id))->assertNotFound();
    }

    public function test_detalhe_do_colaborador_mostra_qualificacoes_e_obrigatorias_em_falta(): void
    {
        $cargo = Cargo::factory()->create();
        $nr35 = QualificacaoTipo::factory()->create(['nome' => 'NR-35', 'validade_meses' => 12]);
        $nr10 = QualificacaoTipo::factory()->create(['nome' => 'NR-10']);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $nr35->id, 'obrigatoria' => true]);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $nr10->id, 'obrigatoria' => true]);
        $colaborador = Colaborador::factory()->create(['cargo_id' => $cargo->id]);
        ColaboradorQualificacao::factory()->create(['colaborador_id' => $colaborador->id, 'qualificacao_tipo_id' => $nr35->id, 'data_conclusao' => '2025-01-01', 'data_validade' => '2026-01-01']);

        $this->actingAs($this->rh())->get(route('colaborador.show', $colaborador->id))->assertOk()
            ->assertSee('Obrigatórias ausentes:')->assertSee('NR-10')
            ->assertSee('Obrigatórias vencidas:')->assertSee('Vencida');
        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('colaborador.show', $colaborador->id))->assertOk()->assertDontSee('Obrigatórias ausentes');
    }

    // --- pendentes ------------------------------------------------------------

    public function test_lista_de_pendentes_com_filtro_por_cargo_e_visibilidade_do_gestor(): void
    {
        $tipo = QualificacaoTipo::factory()->create(['nome' => 'NR-18']);
        $pedreiro = Cargo::factory()->create(['nome' => 'Pedreiro']);
        $servente = Cargo::factory()->create(['nome' => 'Servente']);
        foreach ([$pedreiro, $servente] as $c) {
            CargoQualificacao::factory()->create(['cargo_id' => $c->id, 'qualificacao_tipo_id' => $tipo->id, 'obrigatoria' => true]);
        }
        $ana = Colaborador::factory()->create(['cargo_id' => $pedreiro->id, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Ana Pedreira'])->id]);
        Colaborador::factory()->create(['cargo_id' => $servente->id, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Davi Servente'])->id]);

        $this->actingAs($this->rh())->get(route('qualificacao.pendentes'))->assertOk()->assertSee('Ana Pedreira')->assertSee('Davi Servente')->assertSee('NR-18');
        $this->get(route('qualificacao.pendentes', ['cargo_id' => $pedreiro->id]))->assertOk()->assertSee('Ana Pedreira')->assertDontSee('Davi Servente');

        $gestor = $this->usuarioCom('qualificacao-list', 'colaborador-list', 'equipe-gerir');
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => now()->subMonth()]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $ana->id, 'equipe_id' => $equipe->id, 'inicio' => now()->subMonth()]);

        $this->actingAs($gestor)->get(route('qualificacao.pendentes'))->assertOk()->assertSee('Ana Pedreira')->assertDontSee('Davi Servente');
    }

    public function test_sem_pendencia_a_tela_diz_que_esta_tudo_em_dia(): void
    {
        $this->actingAs($this->rh())->get(route('qualificacao.pendentes'))->assertOk()->assertSee('Nenhuma pendência');
    }
}

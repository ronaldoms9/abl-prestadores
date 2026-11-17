<?php

namespace Tests\Feature\Cadastros;

use App\Enums\AplicaDocumento;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function rh()
    {
        return $this->usuarioCom('documento-list', 'documento-edit', 'pessoa-list', 'colaborador-list');
    }

    private function pdf(string $nome = 'rg.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nome, 120, 'application/pdf');
    }

    public function test_anexa_o_documento_ao_disco_privado_com_os_dados_do_arquivo(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->create(['nome' => 'RG', 'aplica_a' => AplicaDocumento::PessoaFisica]);

        $this->actingAs($this->rh())->post(route('documento.store', ['pessoa-fisica', $pf->id]), [
            'documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf('meu rg.pdf'), 'observacao' => 'frente e verso',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);

        $doc = Documento::firstOrFail();
        $this->assertSame('RG', $doc->tipo);
        $this->assertSame('meu rg.pdf', $doc->arquivo_nome);
        $this->assertSame('frente e verso', $doc->observacao);
        $this->assertStringStartsWith("documentos/{$tipo->id}/{$doc->id}/", $doc->arquivo_path);
        Storage::disk('local')->assertExists($doc->arquivo_path);
        $this->assertTrue($pf->documentos()->first()->is($doc));
        $registro = Activity::where('event', 'documento-anexado')->firstOrFail();
        $this->assertStringNotContainsString('rg.pdf', $registro->properties->toJson());
    }

    public function test_tipo_que_exige_validade_recusa_sem_a_data(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->comValidade()->create(['nome' => 'ASO']);
        $rh = $this->actingAs($this->rh());

        $rh->post(route('documento.store', ['pessoa-fisica', $pf->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'validade'));
        $this->assertSame(0, Documento::count());

        $rh->post(route('documento.store', ['pessoa-fisica', $pf->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf(), 'validade' => '2027-01-31'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('2027-01-31', Documento::firstOrFail()->validade->toDateString());
    }

    public function test_tipo_precisa_servir_ao_cadastro_e_estar_ativo(): void
    {
        $pf = PessoaFisica::factory()->create();
        $soPj = DocumentoTipo::factory()->create(['aplica_a' => AplicaDocumento::PessoaJuridica]);
        $inativo = DocumentoTipo::factory()->create(['ativo' => false]);
        $rh = $this->actingAs($this->rh());

        foreach ([$soPj, $inativo] as $tipo) {
            $rh->post(route('documento.store', ['pessoa-fisica', $pf->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf()], ['Accept' => 'application/json'])->assertStatus(422);
        }

        $this->assertSame(0, Documento::count());
        Storage::disk('local')->assertDirectoryEmpty('documentos');
    }

    public function test_recusa_extensao_nao_permitida_e_arquivo_grande(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->create();
        $rh = $this->actingAs($this->rh());
        $url = route('documento.store', ['pessoa-fisica', $pf->id]);

        $rh->post($url, ['documento_tipo_id' => $tipo->id, 'arquivo' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertStatus(422);
        $rh->post($url, ['documento_tipo_id' => $tipo->id, 'arquivo' => UploadedFile::fake()->create('grande.pdf', 20000, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $rh->post($url, ['documento_tipo_id' => $tipo->id], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Documento::count());
    }

    public function test_anexa_a_pj_e_ao_colaborador(): void
    {
        $pj = PessoaJuridica::factory()->create();
        $colaborador = Colaborador::factory()->create();
        $tipo = DocumentoTipo::factory()->create();
        $rh = $this->actingAs($this->rh());

        $rh->post(route('documento.store', ['pessoa-juridica', $pj->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();
        $rh->post(route('documento.store', ['colaborador', $colaborador->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $pj->documentos()->count());
        $this->assertSame(1, $colaborador->documentos()->count());
    }

    public function test_baixa_o_arquivo_com_o_nome_original_e_so_com_permissao(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->create();
        $this->actingAs($this->rh())->post(route('documento.store', ['pessoa-fisica', $pf->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf('comprovante.pdf')], ['Accept' => 'application/json']);
        $doc = Documento::firstOrFail();

        $this->get(route('documento.baixar', $doc->id))->assertOk()->assertDownload('comprovante.pdf');
        $this->actingAs($this->usuarioCom('pessoa-list'))->get(route('documento.baixar', $doc->id))->assertForbidden();
    }

    public function test_arquivo_ausente_no_disco_da_404(): void
    {
        $doc = Documento::factory()->create(['arquivo_path' => 'documentos/1/1/sumiu.pdf']);

        $this->actingAs($this->usuarioCom('documento-list'))->get(route('documento.baixar', $doc->id))->assertNotFound();
    }

    public function test_gestor_so_baixa_documento_de_colaborador_da_propria_equipe(): void
    {
        $gestor = $this->usuarioCom('documento-list', 'colaborador-list', 'equipe-gerir');
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => now()->subMonth()]);
        $meu = Colaborador::factory()->create();
        $alheio = Colaborador::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $equipe->id, 'inicio' => now()->subMonth()]);

        foreach ([$meu, $alheio] as $c) {
            Storage::disk('local')->put("documentos/x/{$c->id}.pdf", 'conteudo');
        }
        $docMeu = Documento::factory()->create(['documentavel_type' => 'colaborador', 'documentavel_id' => $meu->id, 'arquivo_path' => "documentos/x/{$meu->id}.pdf"]);
        $docAlheio = Documento::factory()->create(['documentavel_type' => 'colaborador', 'documentavel_id' => $alheio->id, 'arquivo_path' => "documentos/x/{$alheio->id}.pdf"]);

        $this->actingAs($gestor)->get(route('documento.baixar', $docMeu->id))->assertOk();
        $this->get(route('documento.baixar', $docAlheio->id))->assertNotFound();
    }

    public function test_exclui_o_registro_e_mantem_o_arquivo(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->create();
        $rh = $this->actingAs($this->rh());
        $rh->post(route('documento.store', ['pessoa-fisica', $pf->id]), ['documento_tipo_id' => $tipo->id, 'arquivo' => $this->pdf()], ['Accept' => 'application/json']);
        $doc = Documento::firstOrFail();

        $rh->deleteJson(route('documento.excluir', $doc->id))->assertStatus(201);

        $this->assertSoftDeleted($doc);
        Storage::disk('local')->assertExists($doc->arquivo_path);
        $this->assertTrue(Activity::where('event', 'documento-excluido')->exists());
    }

    public function test_detalhe_mostra_documentos_e_situacao_so_com_documento_list(): void
    {
        $pf = PessoaFisica::factory()->create();
        Documento::factory()->create(['documentavel_id' => $pf->id, 'tipo' => 'CNH', 'validade' => now()->subDay(), 'arquivo_nome' => 'cnh.pdf']);

        $this->actingAs($this->rh())->get(route('pessoa-fisica.show', $pf->id))->assertOk()->assertSee('CNH')->assertSee('Vencido');
        $this->actingAs($this->usuarioCom('pessoa-list'))->get(route('pessoa-fisica.show', $pf->id))->assertOk()->assertDontSee('CNH')->assertDontSee('Documentos');
    }

    public function test_colaborador_mostra_indicador_de_vencidos_dele_da_pf_e_da_pj(): void
    {
        $colaborador = Colaborador::factory()->create();
        Documento::factory()->create(['documentavel_type' => 'colaborador', 'documentavel_id' => $colaborador->id, 'tipo' => 'ASO', 'validade' => now()->subDays(3)]);
        Documento::factory()->create(['documentavel_type' => 'pessoa-fisica', 'documentavel_id' => $colaborador->pessoa_fisica_id, 'tipo' => 'CNH', 'validade' => now()->subDays(10)]);
        Documento::factory()->create(['documentavel_type' => 'pessoa-juridica', 'documentavel_id' => $colaborador->pessoa_juridica_id, 'tipo' => 'Alvará', 'validade' => now()->subDay()]);
        Documento::factory()->create(['documentavel_type' => 'pessoa-juridica', 'documentavel_id' => $colaborador->pessoa_juridica_id, 'tipo' => 'Certidão', 'validade' => now()->addDays(90)]);

        $this->actingAs($this->rh())->get(route('colaborador.show', $colaborador->id))
            ->assertOk()->assertSee('3 documento(s) vencido(s)');
    }

    // --- tipos ---------------------------------------------------------------

    public function test_crud_dos_tipos_de_documento(): void
    {
        $admin = $this->usuarioCom('documento-tipo-gerir');
        $this->actingAs($admin)->postJson(route('documento-tipo.store'), ['nome' => 'Seguro de vida', 'aplica_a' => 'colaborador', 'exige_validade' => 1])->assertOk();

        $tipo = DocumentoTipo::firstOrFail();
        $this->assertTrue($tipo->exige_validade);
        $this->assertSame(AplicaDocumento::Colaborador, $tipo->aplica_a);

        $this->postJson(route('documento-tipo.store'), ['nome' => 'Seguro de vida', 'aplica_a' => 'todos'])->assertStatus(422);
        $this->postJson(route('documento-tipo.store'), ['nome' => 'Outro', 'aplica_a' => 'marciano'])->assertStatus(422);
        $this->postJson(route('documento-tipo.store', $tipo->id), ['nome' => 'Seguro de vida', 'aplica_a' => 'colaborador', 'exige_validade' => 0, 'ativo' => 0])->assertOk();
        $this->assertFalse($tipo->fresh()->ativo);
        $this->assertFalse($tipo->fresh()->exige_validade);
    }

    public function test_tipo_em_uso_nao_e_excluido(): void
    {
        $tipo = DocumentoTipo::factory()->create();
        Documento::factory()->create(['documento_tipo_id' => $tipo->id]);
        $admin = $this->actingAs($this->usuarioCom('documento-tipo-gerir'));

        $admin->deleteJson(route('documento-tipo.delete', $tipo->id))->assertStatus(400);
        $this->assertNotSoftDeleted($tipo);

        $livre = DocumentoTipo::factory()->create();
        $admin->deleteJson(route('documento-tipo.delete', $livre->id))->assertStatus(201);
    }

    public function test_lista_de_tipos_e_o_formulario_de_anexo_so_oferece_tipos_do_cadastro(): void
    {
        DocumentoTipo::factory()->create(['nome' => 'Só de PJ', 'aplica_a' => AplicaDocumento::PessoaJuridica]);
        DocumentoTipo::factory()->create(['nome' => 'Para todos', 'aplica_a' => AplicaDocumento::Todos]);
        DocumentoTipo::factory()->create(['nome' => 'Tipo inativo', 'ativo' => false]);
        $pf = PessoaFisica::factory()->create();

        $this->actingAs($this->rh())->get(route('pessoa-fisica.show', $pf->id))
            ->assertOk()->assertSee('Para todos')->assertDontSee('Só de PJ')->assertDontSee('Tipo inativo');
    }

    public function test_seeder_cria_o_conjunto_inicial_uma_vez_e_nao_recria_o_que_foi_apagado(): void
    {
        $this->seed(\Database\Seeders\DocumentoTipoSeeder::class);
        $total = DocumentoTipo::count();
        $this->assertGreaterThan(5, $total);
        $this->assertTrue(DocumentoTipo::where('nome', 'like', 'Atestado de Saúde%')->first()->exige_validade);

        DocumentoTipo::where('nome', 'Outros')->first()->delete();
        $this->seed(\Database\Seeders\DocumentoTipoSeeder::class);

        $this->assertSame($total - 1, DocumentoTipo::count());
    }
}

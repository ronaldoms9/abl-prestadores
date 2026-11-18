<?php

namespace Tests\Feature\Notas;

use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Enums\StatusSolicitacaoFerias;
use App\Jobs\GerarZipNotas;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Fechamento;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\NotaFiscal;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Notifications\NotaNaoEnviada;
use App\Notifications\ResumoNotasPendentes;
use App\Notifications\ZipNotasPronto;
use App\Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalNotasTest extends TestCase
{
    use RefreshDatabase;

    private Colaborador $colaborador;

    private User $usuario;

    private Fechamento $fechamento;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-10 09:00:00');

        $this->colaborador = Colaborador::factory()->create([
            'status' => StatusColaborador::Ativo,
            'data_admissao' => '2025-01-01',
            'pessoa_juridica_id' => PessoaJuridica::factory()->create(['cnpj' => '12345678000195']),
            'empresa_id' => Empresa::factory()->create(['cnpj' => '45723174000110']),
        ]);
        $this->usuario = $this->usuarioCom('portal-acessar');
        $this->usuario->update(['pessoa_fisica_id' => $this->colaborador->pessoa_fisica_id]);
        $this->fechamento = Fechamento::factory()->create([
            'colaborador_id' => $this->colaborador->id,
            'competencia_id' => Competencia::factory()->create(['ano_mes' => '2026-09', 'status' => 'aprovada']),
            'total_liquido' => 5300,
            'status' => StatusFechamento::AguardandoNota,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dados(array $extra = []): array
    {
        return $extra + [
            'pdf' => UploadedFile::fake()->create('nota.pdf', 40, 'application/pdf'),
            'numero' => '10', 'serie' => '1', 'data_emissao' => '2026-10-02', 'valor_bruto' => '5300.00',
            'cnpj_emissor' => '12.345.678/0001-95', 'cnpj_tomador' => '45.723.174/0001-10',
        ];
    }

    // ---- Acesso ------------------------------------------------------------

    public function test_quem_so_tem_o_portal_entra_nele_e_os_demais_no_dashboard(): void
    {
        $this->actingAs($this->usuario)->get(route('home'))->assertRedirect(route('portal.index'));
        $this->get(route('portal.index'))->assertOk()->assertSee($this->colaborador->pessoaFisica->nome)->assertSee('Notas fiscais a enviar');

        $this->actingAs($this->usuarioCom('colaborador-list', 'portal-acessar'))->get(route('home'))->assertOk();
    }

    public function test_sem_a_permissao_ou_sem_colaborador_ligado_o_portal_da_403(): void
    {
        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('portal.index'))->assertForbidden();

        $semLigacao = $this->usuarioCom('portal-acessar');
        $this->actingAs($semLigacao)->get(route('portal.index'))->assertForbidden()->assertSee('não está ligado a uma pessoa');

        $comPessoaSemColaborador = $this->usuarioCom('portal-acessar');
        $comPessoaSemColaborador->update(['pessoa_fisica_id' => \App\Models\PessoaFisica::factory()->create()->id]);
        $this->actingAs($comPessoaSemColaborador)->get(route('portal.notas'))->assertForbidden();
    }

    public function test_cada_um_ve_so_os_proprios_dados(): void
    {
        $outro = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
        $fechamentoDele = Fechamento::factory()->create(['colaborador_id' => $outro->id, 'competencia_id' => $this->fechamento->competencia_id, 'total_liquido' => 7777, 'status' => StatusFechamento::AguardandoNota]);

        $this->actingAs($this->usuario)->get(route('portal.notas'))->assertOk()->assertSee('5.300,00')->assertDontSee('7.777,00');
        $this->get(route('portal.nota.form', $fechamentoDele->id))->assertNotFound();
        $this->postJson(route('portal.nota.enviar', $fechamentoDele->id), $this->dados())->assertNotFound();
        $this->assertSame(0, NotaFiscal::count());
    }

    // ---- Notas -------------------------------------------------------------

    public function test_envia_a_nota_pelo_portal_e_acompanha_o_status_ate_a_aprovacao(): void
    {
        $this->actingAs($this->usuario)->get(route('portal.nota.form', $this->fechamento->id))->assertOk()->assertSee('Valor esperado');

        $this->postJson(route('portal.nota.enviar', $this->fechamento->id), $this->dados())->assertOk()->assertJsonPath('data.status', 'enviada');

        $nota = NotaFiscal::firstOrFail();
        $this->assertSame($this->usuario->id, $nota->enviada_por);
        $this->get(route('portal.notas'))->assertOk()->assertSee('Enviada')->assertSee('PDF')->assertDontSee('Enviar nota');
        $this->get(route('portal.nota.form', $this->fechamento->id))->assertForbidden();
        $this->get(route('portal.nota.arquivo', [$nota->id, 'pdf']))->assertOk();
        $this->get(route('portal.nota.arquivo', [$nota->id, 'xml']))->assertNotFound();
    }

    public function test_nota_divergente_mostra_o_motivo_e_recusada_libera_o_reenvio(): void
    {
        $this->actingAs($this->usuario);
        $this->postJson(route('portal.nota.enviar', $this->fechamento->id), $this->dados(['valor_bruto' => '4000']))
            ->assertOk()->assertJsonPath('data.status', 'divergente')->assertJsonFragment(['message' => 'Nota enviada, mas a conferência achou divergências: O valor bruto da nota (R$ 4.000,00) difere do fechamento (R$ 5.300,00).']);
        $this->get(route('portal.notas'))->assertSee('difere do fechamento');

        $nota = NotaFiscal::firstOrFail();
        $nota->update(['status' => StatusNota::Recusada, 'motivo_recusa' => 'Valor não confere']);
        $this->get(route('portal.notas'))->assertSee('Valor não confere')->assertSee('Reenviar');

        $this->postJson(route('portal.nota.enviar', $this->fechamento->id), $this->dados())->assertOk()->assertJsonPath('data.status', 'enviada');
        $this->assertSame(1, NotaFiscal::count());
    }

    public function test_previa_do_xml_mostra_o_que_foi_lido_ou_explica_o_que_deu_errado(): void
    {
        $this->actingAs($this->usuario);
        $xml = UploadedFile::fake()->createWithContent('n.xml', file_get_contents(base_path('tests/Fixtures/nfse/abrasf.xml')));

        $this->postJson(route('portal.nota.previa'), ['xml' => $xml])->assertOk()->assertJsonPath('data.numero', '1234')->assertJsonPath('data.valor_bruto', 'R$ 5.300,00');
        $this->postJson(route('portal.nota.previa'), ['xml' => UploadedFile::fake()->createWithContent('n.xml', 'lixo')])->assertStatus(422);
        $this->postJson(route('portal.nota.previa'), [])->assertStatus(422);
    }

    public function test_envio_exige_pdf_ou_xml_e_recusa_arquivo_que_nao_e_pdf(): void
    {
        $this->actingAs($this->usuario);

        $this->postJson(route('portal.nota.enviar', $this->fechamento->id), ['numero' => '1', 'valor_bruto' => '5300'])->assertStatus(422);
        $this->postJson(route('portal.nota.enviar', $this->fechamento->id), $this->dados(['pdf' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]))->assertStatus(422);
        $this->assertSame(0, NotaFiscal::count());
    }

    // ---- Férias ------------------------------------------------------------

    public function test_colaborador_solicita_e_cancela_ferias_pelo_portal_e_o_gestor_e_avisado(): void
    {
        Notification::fake();
        FeriasNivel::create(['ordem' => 1, 'tipo_aprovador' => 'gestor_equipe', 'ativo' => true]);
        $gestor = User::factory()->create();
        $equipe = \App\Models\Equipe::factory()->create();
        \App\Models\ColaboradorEquipe::factory()->create(['colaborador_id' => $this->colaborador->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => null]);
        \App\Models\EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $periodo = FeriasPeriodo::factory()->create(['colaborador_id' => $this->colaborador->id]);

        $this->actingAs($this->usuario)->get(route('portal.ferias'))->assertOk()->assertSee('Saldo total: <strong>30 dia(s)</strong>', false);
        $this->postJson(route('portal.ferias.solicitar'), ['periodo_id' => $periodo->id, 'inicio' => '2026-11-02', 'fim' => '2026-11-11'])->assertOk();

        $s = FeriasSolicitacao::firstOrFail();
        $this->assertSame([$this->colaborador->id, $this->usuario->id, StatusSolicitacaoFerias::Pendente], [$s->colaborador_id, $s->solicitado_por, $s->status]);
        Notification::assertSentTo($gestor, \App\Notifications\FeriasSolicitada::class);

        $this->postJson(route('portal.ferias.cancelar', $s->id))->assertOk();
        $this->assertSame(StatusSolicitacaoFerias::Cancelada, $s->fresh()->status);
    }

    public function test_nao_solicita_ferias_com_periodo_de_outro_colaborador(): void
    {
        $periodoAlheio = FeriasPeriodo::factory()->create();

        $this->actingAs($this->usuario)->postJson(route('portal.ferias.solicitar'), ['periodo_id' => $periodoAlheio->id, 'inicio' => '2026-11-02', 'fim' => '2026-11-11'])->assertStatus(422);
        $this->assertSame(0, FeriasSolicitacao::count());
    }

    // ---- Alertas e ZIP ----------------------------------------------------

    public function test_alerta_so_depois_do_prazo_e_so_de_quem_ainda_nao_tem_nota_valida(): void
    {
        Notification::fake();
        $financeiro = $this->usuarioCom('alerta-notas');

        Carbon::setTestNow('2026-10-05 08:00:00'); // dia do prazo: ainda não passou
        $this->artisan('alertas:notas')->assertSuccessful();
        Notification::assertNothingSent();

        Carbon::setTestNow('2026-10-06 08:00:00');
        $this->artisan('alertas:notas')->assertSuccessful();
        Notification::assertSentTo($this->usuario, NotaNaoEnviada::class);
        Notification::assertSentTo($financeiro, ResumoNotasPendentes::class);

        Notification::fake();
        NotaFiscal::factory()->create(['fechamento_id' => $this->fechamento->id, 'status' => StatusNota::Enviada]);
        $this->artisan('alertas:notas')->assertSuccessful();
        Notification::assertNotSentTo($this->usuario, NotaNaoEnviada::class);
        Notification::assertNothingSent();
    }

    public function test_prazo_configuravel_e_nota_recusada_continua_sendo_cobrada(): void
    {
        Notification::fake();
        \App\Models\Configuracao::definir('notas.prazo_dia', 20);
        NotaFiscal::factory()->create(['fechamento_id' => $this->fechamento->id, 'status' => StatusNota::Recusada]);

        $this->artisan('alertas:notas')->assertSuccessful();
        Notification::assertNothingSent();

        \App\Models\Configuracao::definir('notas.prazo_dia', 5);
        $this->artisan('alertas:notas')->assertSuccessful();
        Notification::assertSentTo($this->usuario, NotaNaoEnviada::class);
    }

    public function test_zip_em_lote_junta_os_arquivos_das_notas_nao_recusadas_e_avisa_com_link_assinado(): void
    {
        Notification::fake();
        $pdf = 'notas/2026-09/'.$this->colaborador->id.'/a.pdf';
        Storage::disk('local')->put($pdf, 'conteudo-pdf');
        NotaFiscal::factory()->create(['fechamento_id' => $this->fechamento->id, 'numero' => '77', 'pdf_path' => $pdf]);
        $recusada = Fechamento::factory()->create(['competencia_id' => $this->fechamento->competencia_id, 'status' => StatusFechamento::AguardandoNota]);
        Storage::disk('local')->put('notas/x/b.pdf', 'nao-entra');
        NotaFiscal::factory()->create(['fechamento_id' => $recusada->id, 'status' => StatusNota::Recusada, 'pdf_path' => 'notas/x/b.pdf']);
        $quem = $this->usuarioCom('nota-list');

        GerarZipNotas::dispatchSync($this->fechamento->competencia_id, $quem->id);

        $arquivos = Storage::disk('local')->files('notas-zip');
        $this->assertCount(1, $arquivos);
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($arquivos[0]));
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringEndsWith('nota-77.pdf', $zip->getNameIndex(0));
        $this->assertSame('conteudo-pdf', $zip->getFromIndex(0));
        $zip->close();

        // Número digitado à mão com barras não vira caminho dentro do ZIP; ZIPs velhos são apagados.
        NotaFiscal::query()->where('numero', '77')->update(['numero' => '../../x/1']);
        Storage::disk('local')->put('notas-zip/velho.zip', 'x');
        touch(Storage::disk('local')->path('notas-zip/velho.zip'), now()->subDays(3)->getTimestamp());
        GerarZipNotas::dispatchSync($this->fechamento->competencia_id, $quem->id);
        Storage::disk('local')->assertMissing('notas-zip/velho.zip');
        $novo = collect(Storage::disk('local')->files('notas-zip'))->first(fn ($f) => $f !== $arquivos[0]);
        $zip2 = new \ZipArchive;
        $zip2->open(Storage::disk('local')->path($novo));
        $this->assertStringEndsWith('/nota-x1.pdf', $zip2->getNameIndex(0));
        $this->assertStringNotContainsString('..', $zip2->getNameIndex(0));
        $zip2->close();

        Notification::assertSentTo($quem, ZipNotasPronto::class, function ($n) use ($quem) {
            $link = $n->toMail($quem)->actionUrl;
            $this->assertStringContainsString('signature=', $link);

            return true;
        });
    }
}

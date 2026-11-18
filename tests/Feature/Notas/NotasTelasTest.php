<?php

namespace Tests\Feature\Notas;

use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Notifications\NotaRecusada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NotasTelasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fechamento(string $anoMes = '2026-09', float $total = 5300): Fechamento
    {
        $c = Colaborador::factory()->create([
            'status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01',
            'pessoa_juridica_id' => PessoaJuridica::factory()->create(['cnpj' => fake()->unique()->numerify('##############')]),
            'empresa_id' => Empresa::factory()->create(['cnpj' => fake()->unique()->numerify('##############')]),
        ]);

        return Fechamento::factory()->create([
            'colaborador_id' => $c->id,
            'competencia_id' => (Competencia::where('ano_mes', $anoMes)->first() ?? Competencia::factory()->create(['ano_mes' => $anoMes, 'status' => 'aprovada']))->id,
            'total_liquido' => $total, 'status' => StatusFechamento::AguardandoNota,
        ]);
    }

    public function test_lista_por_competencia_com_filtros_de_situacao_e_nome(): void
    {
        $semNota = $this->fechamento();
        $enviada = $this->fechamento();
        NotaFiscal::factory()->create(['fechamento_id' => $enviada->id, 'status' => StatusNota::Enviada, 'numero' => '555']);
        $this->fechamento('2026-08');
        $this->actingAs($this->usuarioCom('nota-list'));
        $nome = fn (Fechamento $f) => $f->fresh('colaborador.pessoaFisica')->colaborador->pessoaFisica->nome;

        $this->get(route('nota.index'))->assertOk()->assertSee($nome($semNota))->assertSee($nome($enviada))->assertSee('555');
        $this->get(route('nota.index', ['status' => 'aguardando']))->assertOk()->assertSee($nome($semNota))->assertDontSee($nome($enviada));
        $this->get(route('nota.index', ['status' => 'enviada']))->assertOk()->assertDontSee($nome($semNota))->assertSee($nome($enviada));
        $this->get(route('nota.index', ['busca' => $nome($enviada)]))->assertOk()->assertSee($nome($enviada))->assertDontSee($nome($semNota));
    }

    public function test_financeiro_aprova_e_recusa_pela_tela_e_rh_so_envia(): void
    {
        Notification::fake();
        $f = $this->fechamento();
        $nota = NotaFiscal::factory()->create(['fechamento_id' => $f->id, 'status' => StatusNota::Divergente, 'divergencias' => [['regra' => 'valor', 'mensagem' => 'Valor difere']]]);
        $rh = $this->usuarioCom('nota-list', 'nota-enviar');
        $financeiro = $this->usuarioCom('nota-list', 'nota-decidir');

        $this->actingAs($rh)->get(route('nota.show', $nota->id))->assertOk()->assertSee('Valor difere');
        $this->get(route('nota.decidir.form', $nota->id))->assertForbidden();
        $this->postJson(route('nota.decidir', $nota->id), ['decisao' => 'aprovar'])->assertForbidden();

        $this->actingAs($financeiro)->get(route('nota.decidir.form', $nota->id))->assertOk()->assertSee('Valor difere');
        $this->postJson(route('nota.decidir', $nota->id), ['decisao' => 'aprovar'])->assertStatus(422);
        $this->postJson(route('nota.decidir', $nota->id), ['decisao' => 'aprovar', 'texto' => 'Combinado com a obra'])->assertOk();
        $this->assertSame(StatusNota::Aprovada, $nota->fresh()->status);
        $this->get(route('nota.decidir.form', $nota->id))->assertForbidden();

        $outra = NotaFiscal::factory()->create(['fechamento_id' => $this->fechamento()->id, 'status' => StatusNota::Enviada]);
        $this->postJson(route('nota.decidir', $outra->id), ['decisao' => 'recusar'])->assertStatus(422);
        $this->postJson(route('nota.decidir', $outra->id), ['decisao' => 'recusar', 'texto' => 'PDF ilegível'])->assertOk();
        $this->assertSame([StatusNota::Recusada, 'PDF ilegível'], [$outra->fresh()->status, $outra->fresh()->motivo_recusa]);
    }

    public function test_rh_envia_em_nome_do_colaborador_e_fica_registrado(): void
    {
        $f = $this->fechamento();
        $c = $f->colaborador()->with(['pessoaJuridica', 'empresa'])->first();
        $rh = $this->usuarioCom('nota-list', 'nota-enviar');

        $this->actingAs($rh)->get(route('nota.enviar.form', $f->id))->assertOk();
        $this->postJson(route('nota.enviar', $f->id), [
            'pdf' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
            'numero' => '5', 'data_emissao' => '2026-10-01', 'valor_bruto' => '5300',
            'cnpj_emissor' => $c->pessoaJuridica->cnpj, 'cnpj_tomador' => $c->empresa->cnpj,
        ])->assertOk()->assertJsonPath('data.status', 'enviada');

        $this->assertSame($rh->id, NotaFiscal::firstOrFail()->enviada_por);
    }

    public function test_download_de_arquivo_por_quem_tem_nota_list_e_zip_exige_link_assinado(): void
    {
        $f = $this->fechamento();
        Storage::disk('local')->put('notas/a.pdf', 'pdf');
        $nota = NotaFiscal::factory()->create(['fechamento_id' => $f->id, 'pdf_path' => 'notas/a.pdf']);

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('nota.arquivo', [$nota->id, 'pdf']))->assertForbidden();
        $this->actingAs($this->usuarioCom('nota-list'))->get(route('nota.arquivo', [$nota->id, 'pdf']))->assertOk();
        $this->get(route('nota.arquivo', [$nota->id, 'xml']))->assertNotFound();

        $eu = auth()->user();
        Storage::disk('local')->put('notas-zip/abc-123.zip', 'zip');
        $this->get(route('nota.zip.baixar', ['arquivo' => 'abc-123.zip']))->assertForbidden();
        $this->get(URL::temporarySignedRoute('nota.zip.baixar', now()->addHour(), ['arquivo' => 'abc-123.zip', 'usuario' => $eu->id]))->assertOk();
        $this->get(URL::temporarySignedRoute('nota.zip.baixar', now()->addHour(), ['arquivo' => 'abc-123.zip', 'usuario' => $eu->id + 99]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('nota.zip.baixar', now()->addHour(), ['arquivo' => '..%2Fsegredo.zip', 'usuario' => $eu->id]))->assertNotFound();
    }

    public function test_botao_de_zip_dispara_o_job(): void
    {
        Queue::fake();
        $f = $this->fechamento();

        $this->actingAs($this->usuarioCom('nota-list'))->postJson(route('nota.zip', $f->competencia_id))->assertOk();

        Queue::assertPushed(\App\Jobs\GerarZipNotas::class);
    }

    public function test_sem_permissao_as_telas_de_notas_dao_403(): void
    {
        $f = $this->fechamento();
        $this->actingAs($this->usuarioCom('colaborador-list'));

        $this->get(route('nota.index'))->assertForbidden();
        $this->get(route('nota.enviar.form', $f->id))->assertForbidden();
        $this->postJson(route('nota.zip', $f->competencia_id))->assertForbidden();
    }
}

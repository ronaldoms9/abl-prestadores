<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Documentos\DocumentosAVencer;
use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Notifications\ResumoDocumentos;
use App\Support\AvisosAdmin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $hoje;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoje = Carbon::parse('2026-11-18');
        Carbon::setTestNow($this->hoje->copy()->setTime(6, 30));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function doc(array $dono = [], string $validade = '2026-12-01', ?DocumentoTipo $tipo = null, string $nome = 'ASO'): Documento
    {
        return Documento::factory()->create($dono + [
            'documento_tipo_id' => ($tipo ?? DocumentoTipo::factory()->comValidade()->create())->id,
            'tipo' => $nome,
            'validade' => $validade,
        ]);
    }

    private function consultar(int $dias = 30): array
    {
        return app(DocumentosAVencer::class)->consultar($dias, $this->hoje);
    }

    public function test_classifica_pelas_bordas_hoje_e_o_ultimo_dia_da_antecedencia(): void
    {
        $vencido = $this->doc(validade: '2026-11-17');            // ontem
        $venceHoje = $this->doc(validade: '2026-11-18');           // a validade é o último dia de uso
        $ultimoDia = $this->doc(validade: '2026-12-18');           // hoje + 30
        $depois = $this->doc(validade: '2026-12-19');              // hoje + 31
        $semValidade = Documento::factory()->create(['validade' => null]);

        $r = $this->consultar();

        $this->assertEqualsCanonicalizing([$vencido->id], $r['vencidos']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$venceHoje->id, $ultimoDia->id], $r['a_vencer']->pluck('id')->all());
        $this->assertFalse($r['a_vencer']->contains($depois));
        $this->assertFalse($r['vencidos']->merge($r['a_vencer'])->contains($semValidade));
    }

    public function test_a_antecedencia_e_um_parametro(): void
    {
        $this->doc(validade: '2026-12-10'); // daqui a 22 dias

        $this->assertCount(0, $this->consultar(10)['a_vencer']);
        $this->assertCount(1, $this->consultar(22)['a_vencer']);
    }

    public function test_a_renovacao_tira_o_documento_antigo_da_lista(): void
    {
        $pf = PessoaFisica::factory()->create();
        $tipo = DocumentoTipo::factory()->comValidade()->create();
        $antigo = $this->doc(['documentavel_id' => $pf->id], '2026-10-01', $tipo, 'CNH');

        $this->assertSame([$antigo->id], $this->consultar()['vencidos']->pluck('id')->all());

        $renovado = $this->doc(['documentavel_id' => $pf->id], '2028-10-01', $tipo, 'CNH');
        $r = $this->consultar();

        $this->assertCount(0, $r['vencidos']);
        $this->assertCount(0, $r['a_vencer']);
        $this->assertNotNull($renovado);
    }

    public function test_tipos_diferentes_do_mesmo_dono_contam_separado(): void
    {
        $pf = PessoaFisica::factory()->create();
        $this->doc(['documentavel_id' => $pf->id], '2026-10-01', null, 'CNH');
        $this->doc(['documentavel_id' => $pf->id], '2026-10-05', null, 'ASO');

        $this->assertCount(2, $this->consultar()['vencidos']);
    }

    public function test_ignora_donos_fora_de_uso(): void
    {
        $desligado = Colaborador::factory()->create(['status' => StatusColaborador::Desligado]);
        $ativo = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $pfInativa = PessoaFisica::factory()->inativo()->create();
        $pjInativa = PessoaJuridica::factory()->inativo()->create();
        $this->doc(['documentavel_type' => 'colaborador', 'documentavel_id' => $desligado->id], '2026-10-01');
        $this->doc(['documentavel_id' => $pfInativa->id], '2026-10-01');
        $this->doc(['documentavel_type' => 'pessoa-juridica', 'documentavel_id' => $pjInativa->id], '2026-10-01');
        $deAtivo = $this->doc(['documentavel_type' => 'colaborador', 'documentavel_id' => $ativo->id], '2026-10-01');

        $this->assertSame([$deAtivo->id], $this->consultar()['vencidos']->pluck('id')->all());
    }

    // --- comando -------------------------------------------------------------

    public function test_envia_um_resumo_unico_a_cada_usuario_ativo_com_a_permissao(): void
    {
        Notification::fake();
        $a = $this->usuarioCom('alerta-documentos');
        $b = $this->usuarioCom('alerta-documentos');
        $inativo = $this->usuarioCom('alerta-documentos');
        $inativo->update(['ativo' => false]);
        $semPermissao = $this->usuarioCom('documento-list');
        $this->doc(validade: '2026-11-01', nome: 'A');
        $this->doc(validade: '2026-11-20', nome: 'B');
        $this->doc(validade: '2026-12-10', nome: 'C');
        $this->doc(validade: '2027-06-01', nome: 'D');

        $this->artisan('alertas:documentos')->assertExitCode(0);

        Notification::assertSentToTimes($a, ResumoDocumentos::class, 1);
        Notification::assertSentToTimes($b, ResumoDocumentos::class, 1);
        Notification::assertNotSentTo([$inativo, $semPermissao], ResumoDocumentos::class);
        Notification::assertSentTo($a, ResumoDocumentos::class, function ($n) use ($a) {
            $mail = $n->toMail($a);
            $texto = implode(' ', array_map(fn ($l) => is_string($l) ? $l : '', $mail->introLines));

            return str_contains($mail->subject, '1 vencido(s) e 2 a vencer') && str_contains($texto, 'Vencidos (1)') && str_contains($texto, 'Vencem em até 30 dias (2)');
        });
    }

    public function test_sem_documentos_vencidos_ou_a_vencer_nao_envia_nada(): void
    {
        Notification::fake();
        $this->usuarioCom('alerta-documentos');
        $this->doc(validade: '2027-06-01');

        $this->artisan('alertas:documentos')->expectsOutputToContain('Nenhum documento')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_sem_destinatario_registra_no_log_e_avisa_o_admin_sem_falhar(): void
    {
        Notification::fake();
        Log::spy();
        $this->doc(validade: '2026-11-01');

        $this->artisan('alertas:documentos')->assertExitCode(0);

        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'alerta-documentos'))->once();
        $this->assertArrayHasKey('alerta-documentos', AvisosAdmin::alertasSemDestinatario());
    }

    public function test_o_aviso_some_quando_alguem_passa_a_ter_a_permissao(): void
    {
        $this->doc(validade: '2026-11-01');
        $this->artisan('alertas:documentos');
        $this->assertNotEmpty(AvisosAdmin::alertasSemDestinatario());

        $this->usuarioCom('alerta-documentos');
        Notification::fake();
        $this->artisan('alertas:documentos');

        $this->assertSame([], AvisosAdmin::alertasSemDestinatario());
    }

    public function test_aviso_aparece_no_painel_so_para_quem_edita_perfis(): void
    {
        AvisosAdmin::registrarAlertaSemDestinatario('alerta-documentos', 'alerta de documentos vencidos e a vencer');

        $this->actingAs($this->usuarioCom('grupo-edit'))->get(route('home'))->assertOk()->assertSee('Ninguém recebe')->assertSee('alerta-documentos');
        $this->actingAs($this->usuarioCom('pessoa-list'))->get(route('home'))->assertOk()->assertDontSee('Ninguém recebe');
    }

    public function test_antecedencia_vem_de_configuracoes_quando_existe(): void
    {
        Notification::fake();
        $a = $this->usuarioCom('alerta-documentos');
        $this->doc(validade: '2027-01-01'); // daqui a 44 dias: fora dos 30 padrão

        $this->artisan('alertas:documentos');
        Notification::assertNothingSent();

        Configuracao::definir('alertas.documentos', ['dias' => 45]);
        $this->artisan('alertas:documentos');
        Notification::assertSentTo($a, ResumoDocumentos::class);
    }

    public function test_o_agendamento_diario_existe(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('alertas:documentos')->assertExitCode(0);
    }
}

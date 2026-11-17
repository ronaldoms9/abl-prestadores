<?php

namespace Tests\Feature\Contratos;

use App\Actions\Contratos\ContratosAVencer;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Actions\Contratos\RenovarContrato;
use App\Actions\Contratos\VigenciaEfetiva;
use App\Console\Commands\AlertasContratos;
use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\User;
use App\Notifications\ResumoContratos;
use App\Support\AvisosAdmin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlertaContratosTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $hoje;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoje = Carbon::parse('2026-11-18');
        Carbon::setTestNow($this->hoje->copy()->setTime(7, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contrato(?string $fim, StatusContrato $status = StatusContrato::Assinado, array $colaborador = []): Contrato
    {
        return Contrato::factory()->gerado()->create([
            'colaborador_id' => Colaborador::factory()->create($colaborador + ['status' => StatusColaborador::Ativo])->id,
            'status' => $status,
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => $fim,
            'numero' => '2026/'.str_pad((string) (Contrato::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
        ]);
    }

    private function aditivoDeProrrogacao(Contrato $pai, string $novaFim, StatusContrato $status = StatusContrato::Gerado): Contrato
    {
        return Contrato::factory()->create([
            'colaborador_id' => $pai->colaborador_id,
            'contrato_pai_id' => $pai->id,
            'tipo' => TipoContrato::Aditivo,
            'motivo' => MotivoAditivo::Prorrogacao,
            'dados' => ['nova_vigencia_fim' => $novaFim],
            'status' => $status,
        ]);
    }

    private function consultar(): array
    {
        return app(ContratosAVencer::class)->consultar([30, 15, 5], $this->hoje);
    }

    public function test_classifica_vencidos_e_marcos_pelas_bordas(): void
    {
        $vencido = $this->contrato('2026-11-17');
        $hoje = $this->contrato('2026-11-18');
        $m5 = $this->contrato('2026-11-23');
        $m15 = $this->contrato('2026-12-03');
        $m30 = $this->contrato('2026-12-18');
        $fora = [$this->contrato('2026-11-19'), $this->contrato('2026-11-24'), $this->contrato('2026-12-19'), $this->contrato('2027-06-01')];
        $this->contrato(null);

        $r = $this->consultar();

        $this->assertSame([$vencido->id], $r['vencidos']->pluck('contrato.id')->all());
        $this->assertSame([-1], $r['vencidos']->pluck('dias')->all());
        $this->assertSame([$hoje->id, $m5->id, $m15->id, $m30->id], $r['marcos']->pluck('contrato.id')->all());
        $this->assertSame([0, 5, 15, 30], $r['marcos']->pluck('dias')->all());
        $this->assertEmpty(array_intersect(array_map(fn ($c) => $c->id, $fora), $r['marcos']->pluck('contrato.id')->all()));
    }

    public function test_ignora_encerrado_rascunho_desligado_aditivo_e_contrato_sem_fim(): void
    {
        $this->contrato('2026-11-10', StatusContrato::Encerrado);
        $this->contrato('2026-11-10', StatusContrato::Rascunho);
        $this->contrato('2026-11-10', colaborador: ['status' => StatusColaborador::Desligado]);
        $this->contrato(null);
        $pai = $this->contrato('2027-12-31');
        Contrato::factory()->gerado('2026/0900')->create(['colaborador_id' => $pai->colaborador_id, 'contrato_pai_id' => $pai->id, 'tipo' => TipoContrato::Aditivo, 'vigencia_fim' => '2026-11-01', 'status' => StatusContrato::Gerado]);

        $r = $this->consultar();

        $this->assertCount(0, $r['vencidos']);
        $this->assertCount(0, $r['marcos']);
    }

    public function test_prorrogacao_gerada_adia_o_vencimento_e_rascunho_nao_conta(): void
    {
        $prorrogado = $this->contrato('2026-11-10');
        $this->aditivoDeProrrogacao($prorrogado, '2027-02-28');
        $soRascunho = $this->contrato('2026-11-10');
        $this->aditivoDeProrrogacao($soRascunho, '2027-02-28', StatusContrato::Rascunho);

        $r = $this->consultar();

        $this->assertSame([$soRascunho->id], $r['vencidos']->pluck('contrato.id')->all());
        $this->assertSame('2027-02-28', app(VigenciaEfetiva::class)->fim($prorrogado)->toDateString());
        $this->assertSame('2026-11-10', app(VigenciaEfetiva::class)->fim($soRascunho)->toDateString());
    }

    public function test_a_maior_data_entre_as_prorrogacoes_vale(): void
    {
        $contrato = $this->contrato('2026-11-10');
        $this->aditivoDeProrrogacao($contrato, '2027-03-31');
        $this->aditivoDeProrrogacao($contrato, '2027-01-31');

        $this->assertSame('2027-03-31', app(VigenciaEfetiva::class)->fim($contrato)->toDateString());
    }

    public function test_comando_envia_um_resumo_a_quem_tem_a_permissao(): void
    {
        Notification::fake();
        $destinatario = $this->usuarioCom(AlertasContratos::PERMISSAO);
        $semPermissao = $this->usuarioCom('contrato-list');
        $this->contrato('2026-11-10');
        $this->contrato('2026-12-03');

        $this->artisan('alertas:contratos')->expectsOutputToContain('1 vencido(s), 1 em marco')->assertSuccessful();

        Notification::assertSentTo($destinatario, ResumoContratos::class, fn (ResumoContratos $n) => str_contains($n->toMail($destinatario)->subject, '1 vencido(s) e 1 a vencer'));
        Notification::assertNotSentTo($semPermissao, ResumoContratos::class);
    }

    public function test_resumo_mostra_colaborador_numero_e_situacao(): void
    {
        $contrato = $this->contrato('2026-11-10');
        $contrato->load('colaborador.pessoaFisica');
        $usuario = User::factory()->create();

        $mensagem = (new ResumoContratos(collect([['contrato' => $contrato, 'fim' => Carbon::parse('2026-11-10'), 'dias' => -8]]), collect(), $this->hoje))->toMail($usuario);
        $texto = implode("\n", $mensagem->introLines);

        $this->assertStringContainsString($contrato->colaborador->pessoaFisica->nome, $texto);
        $this->assertStringContainsString($contrato->numero, $texto);
        $this->assertStringContainsString('venceu há 8 dia(s)', $texto);
    }

    public function test_comando_nao_envia_nada_sem_contrato_a_avisar(): void
    {
        Notification::fake();
        $this->usuarioCom(AlertasContratos::PERMISSAO);
        $this->contrato('2027-06-01');

        $this->artisan('alertas:contratos')->expectsOutputToContain('Nenhum contrato')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_sem_destinatario_registra_no_log_e_avisa_o_administrador(): void
    {
        Notification::fake();
        Log::spy();
        $this->contrato('2026-11-10');

        $this->artisan('alertas:contratos')->assertSuccessful();

        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, AlertasContratos::PERMISSAO))->once();
        $this->assertArrayHasKey(AlertasContratos::PERMISSAO, AvisosAdmin::alertasSemDestinatario());
    }

    public function test_marcos_podem_ser_mudados_na_configuracao(): void
    {
        Notification::fake();
        Configuracao::definir('alertas.contratos', ['dias' => [10]]);
        $destinatario = $this->usuarioCom(AlertasContratos::PERMISSAO);
        $this->contrato('2026-11-28'); // 10 dias
        $this->contrato('2026-12-03'); // 15 dias: fora dos marcos configurados

        $this->artisan('alertas:contratos')->expectsOutputToContain('0 vencido(s), 1 em marco')->assertSuccessful();
        Notification::assertSentToTimes($destinatario, ResumoContratos::class, 1);
    }

    public function test_renovar_cria_o_rascunho_do_aditivo_de_prorrogacao_e_ao_gerar_o_alerta_some(): void
    {
        $pai = $this->contrato('2026-11-30');
        ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'motivo' => MotivoAditivo::Prorrogacao, 'corpo_html' => '<p>Prorroga {{contrato.numero_pai}} até {{aditivo.nova_vigencia_fim}}</p>']);

        $rascunho = app(RenovarContrato::class)->executar($pai, Carbon::parse('2027-05-31'));

        $this->assertSame([TipoContrato::Aditivo, MotivoAditivo::Prorrogacao, StatusContrato::Rascunho], [$rascunho->tipo, $rascunho->motivo, $rascunho->status]);
        $this->assertSame($pai->id, $rascunho->contrato_pai_id);
        $this->assertSame('2026-12-01', $rascunho->vigencia_inicio->toDateString());
        $this->assertSame('2027-05-31', $rascunho->vigencia_fim->toDateString());
    }

    public function test_aditivo_de_prorrogacao_gerado_tira_o_contrato_dos_alertas(): void
    {
        $pai = $this->contrato('2026-11-23'); // marco de 5 dias
        ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'motivo' => MotivoAditivo::Prorrogacao, 'corpo_html' => '<p>Prorroga {{contrato.numero_pai}} até {{aditivo.nova_vigencia_fim}}</p>']);
        $this->assertCount(1, $this->consultar()['marcos']);

        $rascunho = app(RenovarContrato::class)->executar($pai, Carbon::parse('2027-05-31'));
        $gerado = app(GerarContrato::class)->executar($rascunho, $this->hoje);

        $this->assertSame($pai->numero.'-A01', $gerado->numero);
        $this->assertStringContainsString('até 31/05/2027', $gerado->conteudo_renderizado);
        $this->assertCount(0, $this->consultar()['marcos']);
        $this->assertCount(0, $this->consultar()['vencidos']);
    }

    public function test_renovar_valida_tipo_prazo_data_e_modelo(): void
    {
        $renovar = app(RenovarContrato::class);
        $semModelo = $this->contrato('2026-11-30');
        ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'motivo' => null, 'corpo_html' => '<p>x</p>']);

        $casos = [
            'sem prazo' => fn () => $renovar->executar($this->contrato(null), Carbon::parse('2027-01-01')),
            'data não posterior' => fn () => $renovar->executar($semModelo, Carbon::parse('2026-11-30')),
            'data anterior' => fn () => $renovar->executar($semModelo, Carbon::parse('2026-01-01')),
            'não é contrato' => fn () => $renovar->executar(Contrato::factory()->gerado('2026/0800')->create(['tipo' => TipoContrato::Aditivo, 'contrato_pai_id' => $semModelo->id, 'colaborador_id' => $semModelo->colaborador_id]), Carbon::parse('2027-01-01')),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Aceitou: {$nome}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // O modelo genérico (sem motivo) serve quando não há um de prorrogação.
        $this->assertSame(TipoContrato::Aditivo, $renovar->executar($semModelo, Carbon::parse('2027-01-01'))->tipo);
    }

    public function test_renovar_sem_nenhum_modelo_de_aditivo_diz_o_que_fazer(): void
    {
        $pai = $this->contrato('2026-11-30');

        try {
            app(RenovarContrato::class)->executar($pai, Carbon::parse('2027-01-01'));
            $this->fail('Renovou sem modelo');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Modelos', $e->validator->errors()->first('contrato_modelo_id'));
        }
    }

    public function test_modelo_de_aditivo_por_motivo_prefere_o_especifico_e_cai_no_generico(): void
    {
        $generico = ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'nome' => 'Genérico', 'motivo' => null]);
        $reajuste = ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'nome' => 'Reajuste', 'motivo' => MotivoAditivo::Reajuste]);
        ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'nome' => 'Inativo', 'motivo' => MotivoAditivo::Beneficios, 'ativo' => false]);

        $this->assertTrue(ContratoModelo::aditivoPara(MotivoAditivo::Reajuste)->is($reajuste));
        $this->assertTrue(ContratoModelo::aditivoPara(MotivoAditivo::Beneficios)->is($generico));
        $this->assertTrue(ContratoModelo::aditivoPara(MotivoAditivo::MudancaCargo)->is($generico));

        $generico->update(['ativo' => false]);
        $this->assertNull(ContratoModelo::aditivoPara(MotivoAditivo::MudancaCargo));
    }

    public function test_tela_renova_e_devolve_o_rascunho(): void
    {
        $pai = $this->contrato('2026-11-30');
        ContratoModelo::factory()->create(['tipo' => TipoContrato::Aditivo, 'motivo' => MotivoAditivo::Prorrogacao, 'corpo_html' => '<p>x</p>']);
        $this->actingAs($this->usuarioCom('contrato-gerar'));

        $id = $this->postJson(route('contrato.renovar', $pai->id), ['vigencia_fim' => '2027-05-31'])->assertOk()->json('data.id');
        $this->assertSame(StatusContrato::Rascunho, Contrato::findOrFail($id)->status);
        $this->postJson(route('contrato.renovar', $pai->id), ['vigencia_fim' => '2026-01-01'])->assertStatus(422);
    }
}

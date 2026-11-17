<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Enums\StatusColaborador;
use App\Models\Cargo;
use App\Models\CargoQualificacao;
use App\Models\Colaborador;
use App\Models\ColaboradorQualificacao;
use App\Models\Configuracao;
use App\Models\PessoaFisica;
use App\Models\QualificacaoTipo;
use App\Notifications\ResumoQualificacoes;
use App\Support\AvisosAdmin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertaQualificacoesTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $hoje;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoje = Carbon::parse('2026-11-18');
        Carbon::setTestNow($this->hoje->copy()->setTime(6, 45));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cargo com uma exigência obrigatória e um colaborador nele. */
    private function cenario(bool $obrigatoria = true, ?int $meses = 12, array $colaborador = []): array
    {
        $cargo = Cargo::factory()->create();
        $tipo = QualificacaoTipo::factory()->create(['validade_meses' => $meses]);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $tipo->id, 'obrigatoria' => $obrigatoria]);
        $c = Colaborador::factory()->create($colaborador + ['cargo_id' => $cargo->id, 'status' => StatusColaborador::Ativo]);

        return [$c, $tipo, $cargo];
    }

    private function qualificar(Colaborador $c, QualificacaoTipo $t, string $conclusao, ?string $validade): ColaboradorQualificacao
    {
        return ColaboradorQualificacao::factory()->create(['colaborador_id' => $c->id, 'qualificacao_tipo_id' => $t->id, 'data_conclusao' => $conclusao, 'data_validade' => $validade]);
    }

    private function analisar(int $dias = 30)
    {
        return app(SituacaoQualificacoes::class)->analisar($this->hoje, $dias);
    }

    public function test_sem_registro_a_obrigatoria_esta_ausente(): void
    {
        [$c, $tipo] = $this->cenario();

        $s = $this->analisar();

        $this->assertCount(1, $s);
        $this->assertSame([$tipo->id], $s[0]['ausentes']->pluck('id')->all());
        $this->assertSame($c->id, $s[0]['colaborador']->id);
    }

    public function test_classifica_vencida_a_vencer_e_em_dia_pelas_bordas(): void
    {
        [$vencida, $t1] = $this->cenario();
        [$venceHoje, $t2] = $this->cenario();
        [$ultimoDia, $t3] = $this->cenario();
        [$depois, $t4] = $this->cenario();
        $this->qualificar($vencida, $t1, '2025-01-01', '2026-11-17');
        $this->qualificar($venceHoje, $t2, '2025-01-01', '2026-11-18');
        $this->qualificar($ultimoDia, $t3, '2025-01-01', '2026-12-18');
        $this->qualificar($depois, $t4, '2025-01-01', '2026-12-19');

        $s = $this->analisar()->keyBy(fn ($x) => $x['colaborador']->id);

        $this->assertCount(1, $s[$vencida->id]['vencidas']);
        $this->assertCount(1, $s[$venceHoje->id]['a_vencer'], 'a validade é o último dia de uso');
        $this->assertCount(1, $s[$ultimoDia->id]['a_vencer']);
        $this->assertFalse($s->has($depois->id), 'fora da antecedência: em dia');
    }

    public function test_tipo_sem_validade_nunca_vence_e_opcional_nao_gera_pendencia(): void
    {
        [$semValidade, $t] = $this->cenario(meses: null);
        $this->qualificar($semValidade, $t, '2010-01-01', null);
        [$opcional] = $this->cenario(obrigatoria: false);

        $this->assertCount(0, $this->analisar());
        $this->assertNotNull($opcional);
    }

    public function test_a_renovacao_mais_recente_vale_e_tira_a_antiga_da_lista(): void
    {
        [$c, $tipo] = $this->cenario();
        $this->qualificar($c, $tipo, '2024-01-01', '2025-01-01');
        $this->assertCount(1, $this->analisar()[0]['vencidas']);

        $this->qualificar($c, $tipo, '2026-10-01', '2027-10-01');

        $this->assertCount(0, $this->analisar());
    }

    public function test_so_colaboradores_abertos_com_cargo_e_tipo_ativo_entram(): void
    {
        $this->cenario(colaborador: ['status' => StatusColaborador::Desligado]);
        $semCargo = Colaborador::factory()->create(['cargo_id' => null, 'status' => StatusColaborador::Ativo]);
        [$comTipoInativo, $tipo] = $this->cenario();
        $tipo->update(['ativo' => false]);
        [$preAdmissao] = $this->cenario(colaborador: ['status' => StatusColaborador::PreAdmissao]);

        $ids = $this->analisar()->pluck('colaborador.id')->all();

        $this->assertSame([$preAdmissao->id], $ids);
        $this->assertNotNull($semCargo);
    }

    public function test_varias_obrigatorias_do_mesmo_cargo_somam_no_mesmo_colaborador(): void
    {
        [$c, $t1, $cargo] = $this->cenario();
        $t2 = QualificacaoTipo::factory()->create();
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $t2->id, 'obrigatoria' => true]);
        $this->qualificar($c, $t1, '2025-01-01', '2026-01-01');

        $s = $this->analisar();

        $this->assertCount(1, $s);
        $this->assertCount(1, $s[0]['ausentes']);
        $this->assertCount(1, $s[0]['vencidas']);
    }

    // --- comando -------------------------------------------------------------

    public function test_envia_um_resumo_unico_aos_usuarios_ativos_com_a_permissao(): void
    {
        Notification::fake();
        $a = $this->usuarioCom('alerta-qualificacoes');
        $b = $this->usuarioCom('alerta-qualificacoes');
        $inativo = $this->usuarioCom('alerta-qualificacoes');
        $inativo->update(['ativo' => false]);
        $outro = $this->usuarioCom('qualificacao-list');
        [$ausente] = $this->cenario(colaborador: ['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Ausente Silva'])->id]);
        [$vencida, $t] = $this->cenario(colaborador: ['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Vencida Souza'])->id]);
        $this->qualificar($vencida, $t, '2025-01-01', '2026-10-01');

        $this->artisan('alertas:qualificacoes')->assertExitCode(0);

        Notification::assertSentToTimes($a, ResumoQualificacoes::class, 1);
        Notification::assertSentToTimes($b, ResumoQualificacoes::class, 1);
        Notification::assertNotSentTo([$inativo, $outro], ResumoQualificacoes::class);
        Notification::assertSentTo($a, ResumoQualificacoes::class, function ($n) use ($a) {
            $mail = $n->toMail($a);
            $texto = implode(' ', array_map(fn ($l) => is_string($l) ? $l : '', $mail->introLines));

            return str_contains($mail->subject, '1 ausente(s), 1 vencida(s) e 0 a vencer')
                && str_contains($texto, 'Ausente Silva') && str_contains($texto, 'Vencida Souza') && str_contains($texto, 'venceu em 01/10/2026');
        });
        $this->assertNotNull($ausente);
    }

    public function test_sem_pendencia_nao_envia_nada(): void
    {
        Notification::fake();
        $this->usuarioCom('alerta-qualificacoes');
        [$c, $t] = $this->cenario();
        $this->qualificar($c, $t, '2026-10-01', '2027-10-01');

        $this->artisan('alertas:qualificacoes')->expectsOutputToContain('Nenhuma qualificação')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_sem_destinatario_avisa_o_admin_e_o_aviso_some_quando_ha_alguem(): void
    {
        Notification::fake();
        $this->cenario();

        $this->artisan('alertas:qualificacoes')->assertExitCode(0);
        Notification::assertNothingSent();
        $this->assertArrayHasKey('alerta-qualificacoes', AvisosAdmin::alertasSemDestinatario());

        $this->usuarioCom('alerta-qualificacoes');
        $this->artisan('alertas:qualificacoes');
        $this->assertArrayNotHasKey('alerta-qualificacoes', AvisosAdmin::alertasSemDestinatario());
    }

    public function test_antecedencia_vem_de_configuracoes_quando_existe(): void
    {
        Notification::fake();
        $u = $this->usuarioCom('alerta-qualificacoes');
        [$c, $t] = $this->cenario();
        $this->qualificar($c, $t, '2025-01-01', '2027-01-01'); // daqui a 44 dias

        $this->artisan('alertas:qualificacoes');
        Notification::assertNothingSent();

        Configuracao::definir('alertas.qualificacoes', ['dias' => 45]);
        $this->artisan('alertas:qualificacoes');
        Notification::assertSentTo($u, ResumoQualificacoes::class);
    }

    public function test_o_agendamento_diario_existe(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('alertas:qualificacoes')->assertExitCode(0);
    }
}

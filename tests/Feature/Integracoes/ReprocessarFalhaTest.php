<?php

namespace Tests\Feature\Integracoes;

use App\Enums\DirecaoIntegracao;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Integrations\Uau\Jobs\ImportarPessoaJob;
use App\Integrations\Uau\OperacaoUau;
use App\Models\IntegracaoLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReprocessarFalhaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->usuario = $this->usuarioCom('integracao-list', 'integracao-reenviar');
    }

    public function test_falha_de_empresas_e_obras_despacha_o_job_certo(): void
    {
        $this->reprocessar($this->falha(OperacaoUau::EmpresasAtivas))->assertOk()->assertJson(['success' => true]);
        $this->reprocessar($this->falha(OperacaoUau::ObrasAtivas))->assertOk();

        Queue::assertPushedOn('uau', ImportarEmpresasJob::class);
        Queue::assertPushedOn('uau', ImportarObrasJob::class);
    }

    public function test_falha_de_pessoa_despacha_com_o_documento_consultado(): void
    {
        $log = $this->falha(OperacaoUau::PessoaPorDocumento, ['payload' => ['cpf_cnpj' => '52998224725', 'status' => 2], 'chave_idempotencia' => 'pessoa:52998224725']);

        $this->reprocessar($log)
            ->assertOk()
            ->assertJson(['message' => 'Pessoa por CPF/CNPJ: enviado para a fila. O resultado aparece nesta lista em instantes.']);

        Queue::assertPushedOn('uau', ImportarPessoaJob::class, fn (ImportarPessoaJob $job) => $job->documento === '52998224725');
        $this->assertDatabaseHas('activity_log', [
            'event' => 'integracao-reprocessada',
            'subject_type' => 'integracao-log',
            'subject_id' => $log->id,
            'causer_id' => $this->usuario->id,
        ]);
    }

    public function test_recusas_com_o_motivo(): void
    {
        $resolvida = $this->falha(OperacaoUau::EmpresasAtivas, ['executado_em' => now()->subHour()]);
        IntegracaoLog::factory()->sucesso()->create(['operacao' => 'empresas-ativas', 'executado_em' => now()]);

        $casos = [
            'Esta falha já foi resolvida por uma chamada posterior.' => $resolvida,
            'Só uma chamada com erro pode ser reprocessada.' => IntegracaoLog::factory()->sucesso()->create(['operacao' => 'obras-ativas']),
            'Esta chamada não é uma importação; a próxima chamada ao UAU a repete.' => $this->falha(OperacaoUau::Autenticar),
            'Só as importações do UAU podem ser reprocessadas.' => $this->falha(OperacaoUau::ObrasAtivas, ['direcao' => DirecaoIntegracao::Saida]),
            'A chamada não guardou o CPF/CNPJ consultado.' => $this->falha(OperacaoUau::PessoaPorDocumento, ['payload' => null]),
        ];

        foreach ($casos as $mensagem => $log) {
            $this->reprocessar($log)->assertStatus(422)->assertJson(['success' => false, 'message' => $mensagem]);
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('activity_log', ['event' => 'integracao-reprocessada']);
    }

    public function test_botao_aparece_so_com_a_permissao_e_quando_cabe(): void
    {
        $falha = $this->falha(OperacaoUau::EmpresasAtivas);
        $autenticacao = $this->falha(OperacaoUau::Autenticar, ['executado_em' => now()->subMinute()]);

        $comPermissao = $this->acoes($this->usuario);
        $semPermissao = $this->acoes($this->usuarioCom('integracao-list'));

        $this->assertStringContainsString(route('integracao.reprocessar', $falha->id), $comPermissao[$falha->id]);
        $this->assertStringNotContainsString('Reprocessar', $comPermissao[$autenticacao->id]);
        $this->assertStringNotContainsString('Reprocessar', $semPermissao[$falha->id]);
    }

    private function falha(OperacaoUau $operacao, array $extra = []): IntegracaoLog
    {
        return IntegracaoLog::factory()->erro()->create(['operacao' => $operacao->value, ...$extra]);
    }

    private function reprocessar(IntegracaoLog $log)
    {
        return $this->actingAs($this->usuario)->postJson(route('integracao.reprocessar', $log->id));
    }

    /** @return array<int, string> id do log => HTML das ações */
    private function acoes(User $usuario): array
    {
        $linhas = $this->actingAs($usuario)
            ->getJson(route('integracao.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->json('data');

        // A ordem das linhas não importa: o id vem do link de detalhes.
        return collect($linhas)
            ->mapWithKeys(fn (array $linha) => [(int) preg_replace('/.*integracoes\/(\d+).*/s', '$1', $linha['action']) => $linha['action']])
            ->all();
    }
}

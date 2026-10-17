<?php

namespace Tests\Feature\Uau;

use App\Enums\StatusIntegracao;
use App\Integrations\Uau\ContextoChamada;
use App\Integrations\Uau\OperacaoUau;
use App\Integrations\Uau\RegistroIntegracao;
use App\Models\PessoaFisica;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistroIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_a_chamada_sem_segredos_e_com_milissegundos(): void
    {
        $log = $this->registrar(
            payload: ['Login' => 'abl', 'Senha' => 'segredo-123'],
            resposta: '{"codigoPessoa":1,"senhaUauWeb":"x","dadosBancarios":[{"conta":"9"}]}',
        );

        $log->refresh();
        $this->assertSame(['Login' => 'abl', 'Senha' => '[oculto]'], $log->payload);
        $this->assertSame(['codigoPessoa' => 1, 'senhaUauWeb' => '[oculto]', 'dadosBancarios' => '[oculto]'], $log->resposta);
        $this->assertSame('10:00:00.123', $log->executado_em->format('H:i:s.v'));
        $this->assertSame('pessoa-por-documento', $log->operacao);
        $this->assertSame(StatusIntegracao::Sucesso, $log->status);
        $this->assertSame(3, $log->tentativas);
        $this->assertSame('chave-1', $log->chave_idempotencia);
        $this->assertSame('5b0a1f0e-1111-4c2a-9a55-0e8d6f6a0001', $log->correlacao);
    }

    public function test_entidade_gravada_com_o_apelido_do_mapa_polimorfico(): void
    {
        $pessoa = PessoaFisica::factory()->create();

        $log = $this->registrar(contexto: new ContextoChamada($pessoa));

        $this->assertSame('pessoa-fisica', $log->entidade_type);
        $this->assertSame($pessoa->id, $log->entidade_id);
    }

    public function test_resposta_que_nao_e_json_vira_bruto_e_escalar_vira_valor(): void
    {
        $this->assertSame(['bruto' => '<html>Erro 502</html>'], $this->registrar(resposta: '<html>Erro 502</html>')->fresh()->resposta);
        $this->assertSame(['valor' => '1.0.4'], $this->registrar(resposta: '"1.0.4"')->fresh()->resposta);
        $this->assertSame(['bruto' => str_repeat('x', 2000).'...'], $this->registrar(resposta: str_repeat('x', 3000))->fresh()->resposta);
        $this->assertNull($this->registrar(resposta: '')->fresh()->resposta);
    }

    public function test_erro_cortado_em_2000_caracteres(): void
    {
        $log = $this->registrar(status: StatusIntegracao::Erro, erro: str_repeat('e', 5000));

        $this->assertSame(2003, mb_strlen($log->fresh()->erro));
    }

    public function test_falha_ao_gravar_nao_derruba_a_chamada(): void
    {
        Exceptions::fake();
        Schema::drop('integracao_logs');

        $this->assertNull($this->registrar());
        Exceptions::assertReported(fn (QueryException $e) => str_contains($e->getMessage(), 'integracao_logs'));
    }

    private function registrar(
        ?array $payload = ['cpf_cnpj' => '52998224725', 'status' => 2],
        mixed $resposta = '{"ok":true}',
        StatusIntegracao $status = StatusIntegracao::Sucesso,
        ?string $erro = null,
        ?ContextoChamada $contexto = null,
    ) {
        return app(RegistroIntegracao::class)->registrar(
            operacao: OperacaoUau::PessoaPorDocumento,
            rota: 'Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus',
            contexto: $contexto ?? new ContextoChamada(chaveIdempotencia: 'chave-1', tentativa: 3),
            payload: $payload,
            resposta: $resposta,
            httpStatus: 200,
            status: $status,
            erro: $erro,
            executadoEm: CarbonImmutable::parse('2026-10-17 10:00:00.123'),
            duracaoMs: 42,
            correlacao: '5b0a1f0e-1111-4c2a-9a55-0e8d6f6a0001',
        );
    }
}

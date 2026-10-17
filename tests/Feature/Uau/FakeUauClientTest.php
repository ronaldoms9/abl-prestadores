<?php

namespace Tests\Feature\Uau;

use App\Enums\StatusIntegracao;
use App\Integrations\Uau\Data\TipoPessoaUau;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\FakeUauClient;
use App\Integrations\Uau\OperacaoUau;
use App\Integrations\Uau\UauClient;
use App\Integrations\Uau\UauGateway;
use App\Models\IntegracaoLog;
use App\Models\PessoaFisica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeUauClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolve_os_dados_de_exemplo(): void
    {
        $uau = $this->fake();

        $this->assertCount(3, $uau->empresasAtivas());
        $this->assertCount(4, $uau->obrasAtivas());
        $this->assertSame('UAU API 1.0 (simulada)', $uau->versao());

        $pj = $uau->pessoaPorDocumento('27.543.198/0001-06');
        $this->assertSame(TipoPessoaUau::Juridica, $pj->tipo);
        $this->assertSame('MAR Engenharia', $pj->nomeFantasia);
    }

    public function test_documento_sem_arquivo_e_pessoa_nao_encontrada(): void
    {
        $this->assertNull($this->fake()->pessoaPorDocumento('11144477735'));
        $this->assertSame(StatusIntegracao::Sucesso, IntegracaoLog::sole()->status);
    }

    public function test_grava_o_log_com_rota_fake_e_sem_segredos(): void
    {
        $this->fake()->pessoaPorDocumento('03174682940');

        $log = IntegracaoLog::sole();
        $this->assertSame('fake:Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus', $log->rota);
        $this->assertSame('pessoa-por-documento', $log->operacao);
        $this->assertSame(['cpf_cnpj' => '03174682940', 'status' => 2], $log->payload);
        $this->assertSame('[oculto]', $log->resposta['senhaUauWeb']);
        $this->assertSame('[oculto]', $log->resposta['dadosBancarios']);
        $this->assertStringNotContainsString('segredo-uau-web-123', IntegracaoLog::all()->toJson());
    }

    public function test_registra_as_chamadas_inclusive_das_copias_com_contexto(): void
    {
        $pessoa = PessoaFisica::factory()->create(['cpf' => '52998224725']);
        $uau = $this->fake();

        $uau->empresasAtivas();
        $uau->comContexto(entidade: $pessoa, tentativa: 2)->pessoaPorDocumento('52998224725');

        $this->assertCount(2, $uau->chamadas());
        [$chamada] = $uau->chamadas(OperacaoUau::PessoaPorDocumento);
        $this->assertSame(['cpf_cnpj' => '52998224725', 'status' => 2], $chamada['argumentos']);
        $this->assertTrue($chamada['contexto']->entidade->is($pessoa));
        $this->assertSame(2, $chamada['contexto']->tentativa);
        $this->assertSame('pessoa-fisica', IntegracaoLog::where('operacao', 'pessoa-por-documento')->sole()->entidade_type);
    }

    public function test_falhar_em_lanca_o_erro_as_vezes_pedidas_e_depois_volta_ao_normal(): void
    {
        $uau = $this->fake()->falharEm(OperacaoUau::EmpresasAtivas, new UauIndisponivel('UAU fora do ar.', 503), vezes: 2);

        foreach ([1, 2] as $vez) {
            try {
                $uau->empresasAtivas();
                $this->fail("A chamada {$vez} deveria falhar.");
            } catch (UauIndisponivel $e) {
                $this->assertSame('UAU fora do ar.', $e->getMessage());
            }
        }

        $this->assertCount(3, $uau->empresasAtivas());
        $this->assertCount(4, $uau->obrasAtivas(), 'a falha é só da operação programada');
        $this->assertSame(
            ['erro', 'erro', 'sucesso', 'sucesso'],
            IntegracaoLog::orderBy('id')->pluck('status')->map->value->all(),
        );
    }

    public function test_uau_fake_escolhe_a_implementacao(): void
    {
        config(['uau.fake' => true]);
        $this->assertInstanceOf(FakeUauClient::class, app(UauGateway::class));

        $this->app->forgetInstance(UauGateway::class);
        config(['uau' => array_merge(config('uau'), [
            'fake' => false,
            'base_url' => 'https://uau.test/uauAPI/api/v1.0',
            'login' => 'ablapi',
            'senha' => 'x',
            'token_integracao' => 'y',
        ])]);
        $this->assertInstanceOf(UauClient::class, app(UauGateway::class));
    }

    private function fake(): FakeUauClient
    {
        return app(FakeUauClient::class);
    }
}

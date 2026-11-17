<?php

namespace Tests\Feature\Cadastros;

use App\Integrations\ConsultaCnpj\ConsultaCnpj;
use App\Integrations\ConsultaCnpj\ConsultaCnpjIndisponivel;
use App\Integrations\ConsultaCnpj\DadosCnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultaCnpjTest extends TestCase
{
    use RefreshDatabase;

    /** Resposta da BrasilAPI no formato real, com os campos que usamos. */
    private function resposta(array $extra = []): array
    {
        return $extra + [
            'cnpj' => '33000167000101',
            'razao_social' => 'PETROLEO BRASILEIRO S A PETROBRAS',
            'nome_fantasia' => 'PETROBRAS - EDISE',
            'email' => null,
            'ddd_telefone_1' => '2121660000',
            'descricao_situacao_cadastral' => 'ATIVA',
            'cep' => '20031170',
            'descricao_tipo_de_logradouro' => 'AVENIDA',
            'logradouro' => 'REPUBLICA DO CHILE',
            'numero' => '65',
            'complemento' => '',
            'bairro' => 'CENTRO',
            'municipio' => 'RIO DE JANEIRO',
            'uf' => 'RJ',
            'opcao_pelo_mei' => null,
            'opcao_pelo_simples' => null,
            'regime_tributario' => [['ano' => 2023, 'forma_de_tributacao' => 'LUCRO REAL'], ['ano' => 2019, 'forma_de_tributacao' => 'LUCRO PRESUMIDO']],
        ];
    }

    public function test_converte_a_resposta_em_campos_do_cadastro(): void
    {
        $dados = DadosCnpj::daBrasilApi($this->resposta());

        $this->assertSame('33000167000101', $dados->cnpj);
        $this->assertSame('PETROLEO BRASILEIRO S A PETROBRAS', $dados->razaoSocial);
        $this->assertSame('2121660000', $dados->telefone);
        $this->assertNull($dados->email);
        $this->assertSame('Lucro Real', $dados->regimeTributario, 'vale o ano mais recente');
        $this->assertSame('Avenida Republica Do Chile', $dados->endereco['logradouro']);
        $this->assertSame('Rio De Janeiro', $dados->endereco['cidade']);
        $this->assertSame('RJ', $dados->endereco['uf']);
        $this->assertNull($dados->endereco['complemento']);
        $this->assertTrue($dados->ativa());
    }

    public function test_mei_e_simples_tem_prioridade_sobre_o_historico(): void
    {
        $this->assertSame('MEI', DadosCnpj::daBrasilApi($this->resposta(['opcao_pelo_mei' => true, 'opcao_pelo_simples' => true]))->regimeTributario);
        $this->assertSame('Simples Nacional', DadosCnpj::daBrasilApi($this->resposta(['opcao_pelo_simples' => true]))->regimeTributario);
        $this->assertNull(DadosCnpj::daBrasilApi($this->resposta(['regime_tributario' => []]))->regimeTributario);
    }

    public function test_situacao_diferente_de_ativa_e_sinalizada(): void
    {
        $this->assertFalse(DadosCnpj::daBrasilApi($this->resposta(['descricao_situacao_cadastral' => 'BAIXADA']))->ativa());
    }

    public function test_consulta_a_api_e_guarda_em_cache(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response($this->resposta(), 200)]);

        $consulta = app(ConsultaCnpj::class);
        $primeira = $consulta->consultar('33000167000101');
        $segunda = $consulta->consultar('33000167000101');

        $this->assertSame('PETROLEO BRASILEIRO S A PETROBRAS', $primeira->razaoSocial);
        $this->assertEquals($primeira, $segunda);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cnpj/v1/33000167000101'));
    }

    public function test_cnpj_nao_encontrado_vira_null_e_tambem_fica_em_cache(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['type' => 'not_found'], 404)]);

        $consulta = app(ConsultaCnpj::class);

        $this->assertNull($consulta->consultar('00000000000191'));
        $this->assertNull($consulta->consultar('00000000000191'));
        Http::assertSentCount(1);
    }

    public function test_erro_da_api_e_falha_de_rede_nao_vao_para_o_cache(): void
    {
        Cache::flush();
        Http::fakeSequence()->push('erro', 503)->pushFailedConnection()->push($this->resposta(), 200);
        $consulta = app(ConsultaCnpj::class);

        foreach ([503, 'rede'] as $falha) {
            try {
                $consulta->consultar('33000167000101');
                $this->fail("Deveria falhar: {$falha}");
            } catch (ConsultaCnpjIndisponivel) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNotNull($consulta->consultar('33000167000101'), 'a falha anterior não ficou guardada');
    }

    public function test_endpoint_devolve_os_dados_para_o_formulario(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response($this->resposta(), 200)]);

        $this->actingAs($this->usuarioCom('pessoa-create'))
            ->getJson(route('pessoa-juridica.consultar-cnpj', ['cnpj' => '33.000.167/0001-01']))
            ->assertOk()
            ->assertJsonPath('encontrado', true)
            ->assertJsonPath('dados.razao_social', 'PETROLEO BRASILEIRO S A PETROBRAS')
            ->assertJsonPath('dados.ativa', true)
            ->assertJsonPath('dados.endereco.uf', 'RJ');
    }

    public function test_endpoint_recusa_cnpj_invalido_sem_chamar_a_api(): void
    {
        Http::fake();

        $this->actingAs($this->usuarioCom('pessoa-create'))
            ->getJson(route('pessoa-juridica.consultar-cnpj', ['cnpj' => '11.111.111/1111-11']))
            ->assertStatus(422)
            ->assertJsonPath('encontrado', false);

        Http::assertNothingSent();
    }

    public function test_falha_da_consulta_avisa_e_nao_bloqueia_o_cadastro(): void
    {
        Cache::flush();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $usuario = $this->usuarioCom('pessoa-create');
        $this->actingAs($usuario)
            ->getJson(route('pessoa-juridica.consultar-cnpj', ['cnpj' => '33000167000101']))
            ->assertOk()
            ->assertJsonPath('encontrado', false)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'à mão'));

        $this->assertNotNull($usuario->fresh());
    }

    public function test_cnpj_nao_encontrado_avisa_para_preencher_a_mao(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 404)]);

        $this->actingAs($this->usuarioCom('pessoa-edit'))
            ->getJson(route('pessoa-juridica.consultar-cnpj', ['cnpj' => '33000167000101']))
            ->assertOk()
            ->assertJsonPath('encontrado', false)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'não encontrado'));
    }
}

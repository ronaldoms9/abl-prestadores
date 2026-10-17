<?php

namespace Tests\Unit\Uau;

use App\Integrations\Uau\Data\EmpresaUau;
use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Data\TipoPessoaUau;
use App\Integrations\Uau\RespostaUau;
use App\Support\Endereco;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DtosTest extends TestCase
{
    public function test_empresas_dos_dados_de_exemplo(): void
    {
        $empresas = array_map(EmpresaUau::fromArray(...), RespostaUau::linhas($this->exemplo('empresas-ativas.json')));

        $this->assertCount(3, $empresas);
        $this->assertSame(1, $empresas[0]->codigo);
        $this->assertSame('Construtora Horizonte Ltda', $empresas[0]->razaoSocial);
        $this->assertSame('04252011000110', $empresas[1]->cnpj);
        $this->assertNull($empresas[1]->inscricaoEstadual, 'texto vazio vira null');
        $this->assertNull($empresas[1]->telefone);
    }

    public function test_obras_dos_dados_de_exemplo(): void
    {
        $obras = array_map(ObraUau::fromArray(...), RespostaUau::linhas($this->exemplo('obras-ativas.json')));

        $this->assertCount(4, $obras);
        $this->assertSame(['OB01', 1], [$obras[0]->codigo, $obras[0]->empresaCodigo]);
        $this->assertSame(['OB01', 2], [$obras[1]->codigo, $obras[1]->empresaCodigo]);
        $this->assertSame(1, $obras[0]->tipo);
        $this->assertSame('2025-03-01', $obras[0]->dataInicio->toDateString());
        $this->assertNull($obras[2]->dataFim, 'data vazia do .NET vira null');
        $this->assertNull($obras[2]->alteradaEm);
        $this->assertSame(7, $obras[3]->empresaCodigo);
    }

    public function test_pessoa_fisica_com_endereco_e_telefone_principais(): void
    {
        $pessoa = PessoaUau::fromArray($this->exemplo('pessoas/52998224725.json'));

        $this->assertSame(1001, $pessoa->codigo);
        $this->assertSame(TipoPessoaUau::Fisica, $pessoa->tipo);
        $this->assertSame('52998224725', $pessoa->documento);
        $this->assertTrue($pessoa->ativa);
        $this->assertSame('1988-05-17', $pessoa->nascimento->toDateString());
        $this->assertSame('2026-09-01 08:30:00', $pessoa->alteradaEm->toDateTimeString());

        $endereco = $pessoa->enderecoPrincipal();
        $this->assertSame('Rua T-37', $endereco->logradouro, 'o principal (tipo 0), não o primeiro da lista');
        $this->assertInstanceOf(Endereco::class, $endereco->toEndereco());
        $this->assertSame('74230020', $endereco->toEndereco()->cep);
        $this->assertSame('62991234567', $pessoa->telefonePrincipal(), 'o celular, não o residencial');
    }

    public function test_pessoa_inativa_e_pessoa_juridica(): void
    {
        $this->assertFalse(PessoaUau::fromArray($this->exemplo('pessoas/74620153826.json'))->ativa);

        $pj = PessoaUau::fromArray($this->exemplo('pessoas/27543198000106.json'));
        $this->assertSame(TipoPessoaUau::Juridica, $pj->tipo);
        $this->assertSame('27543198000106', $pj->documento);
        $this->assertSame('MAR Engenharia', $pj->nomeFantasia);
        $this->assertNull($pj->nascimento);
        $this->assertSame('Av. 85', $pj->enderecoPrincipal()->logradouro, 'sem principal, o primeiro');
        $this->assertSame('6233334444', $pj->telefonePrincipal(), 'sem celular, o primeiro');
    }

    public function test_documento_mascarado_ou_sem_zero_a_esquerda_sai_completo(): void
    {
        $this->assertSame('03174682940', PessoaUau::fromArray($this->exemplo('pessoas/03174682940.json'))->documento);

        $semZero = PessoaUau::fromArray(['codigoPessoa' => 9, 'tipo' => 0, 'cpfCnpj' => 3174682940, 'nomePessoa' => 'X']);
        $this->assertSame('03174682940', $semZero->documento);

        $cnpj = PessoaUau::fromArray(['codigoPessoa' => 9, 'tipo' => 1, 'cpfCnpj' => '4.252.011/0001-10', 'nomePessoa' => 'Y']);
        $this->assertSame('04252011000110', $cnpj->documento);
    }

    public function test_senha_login_do_portal_e_dados_bancarios_nao_entram_no_dto(): void
    {
        $pessoa = PessoaUau::fromArray($this->exemplo('pessoas/03174682940.json'));
        $serializado = json_encode((array) $pessoa, JSON_UNESCAPED_UNICODE).serialize($pessoa);

        $this->assertStringNotContainsString('segredo-uau-web-123', $serializado);
        $this->assertStringNotContainsString('joaoweb', $serializado);
        $this->assertStringNotContainsString('56789-0', $serializado);
    }

    public function test_campos_sem_diferenciar_maiusculas(): void
    {
        $empresa = EmpresaUau::fromArray(['codigo_emp' => '5', 'DESC_EMP' => 'Teste']);

        $this->assertSame(5, $empresa->codigo);
        $this->assertSame('Teste', $empresa->razaoSocial);
    }

    public function test_linha_sem_identificador_e_recusada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cod_obr');

        ObraUau::fromArray(['Empresa_obr' => 1, 'Descr_obr' => 'Sem código']);
    }

    private function exemplo(string $arquivo): mixed
    {
        return json_decode(file_get_contents(__DIR__.'/../../../resources/fixtures/uau/'.$arquivo), true, flags: JSON_THROW_ON_ERROR);
    }
}

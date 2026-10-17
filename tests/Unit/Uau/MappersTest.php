<?php

namespace Tests\Unit\Uau;

use App\Actions\Uau\ResumoImportacao;
use App\Integrations\Uau\Data\EmpresaUau;
use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Mappers\EmpresaMapper;
use App\Integrations\Uau\Mappers\ObraMapper;
use App\Integrations\Uau\Mappers\PessoaMapper;
use App\Integrations\Uau\RespostaUau;
use App\Models\Empresa;
use App\Models\PessoaJuridica;
use App\Support\Endereco;
use Tests\TestCase;

class MappersTest extends TestCase
{
    public function test_empresa_vira_os_atributos_da_tabela(): void
    {
        [$horizonte, $cerrado] = $this->empresas();

        $this->assertEquals([
            'uau_codigo' => 1,
            'cnpj' => '11222333000181',
            'razao_social' => 'Construtora Horizonte Ltda',
            'inscricao_estadual' => '104567890',
            'inscricao_municipal' => '2345678',
            'telefone' => '(62) 3201-4500',
            'endereco' => new Endereco(logradouro: 'Av. T-63, 1234, Setor Bueno, Goiânia - GO, 74230-100'),
        ], EmpresaMapper::atributos($horizonte));

        $atributos = EmpresaMapper::atributos($cerrado);
        $this->assertSame('04252011000110', $atributos['cnpj'], 'CNPJ com zero à esquerda');
        $this->assertNull($atributos['inscricao_estadual'], 'campo vazio grava null');
        $this->assertNull($atributos['telefone']);
    }

    public function test_endereco_em_texto_unico_vai_para_o_logradouro(): void
    {
        $endereco = EmpresaMapper::atributos($this->empresas()[1])['endereco'];

        $this->assertSame('Rua 9, 150, Setor Oeste, Goiânia - GO', $endereco->logradouro);
        $this->assertNull($endereco->cidade);

        $semEndereco = new EmpresaUau(codigo: 9, razaoSocial: 'Sem endereço');
        $this->assertNull(EmpresaMapper::atributos($semEndereco)['endereco']);
    }

    public function test_obra_vira_os_atributos_da_tabela(): void
    {
        $obras = array_map(ObraUau::fromArray(...), RespostaUau::linhas($this->exemplo('obras-ativas.json')));
        $empresa = (new Empresa())->forceFill(['id' => 42]);

        $this->assertEquals([
            'empresa_id' => 42,
            'uau_codigo' => 'OB01',
            'descricao' => 'Residencial Jardim das Flores',
            'status_uau' => 0,
            'tipo_uau' => '1',
            'data_inicio' => '2025-03-01',
            'data_fim' => '2027-06-30',
            'endereco' => new Endereco(logradouro: 'Rua C-140, Qd 20, Jardim América, Goiânia - GO'),
        ], ObraMapper::atributos($obras[0], $empresa));

        $this->assertNull(ObraMapper::atributos($obras[2], $empresa)['data_fim'], 'data vazia do .NET grava null');
    }

    public function test_pessoa_fisica_vira_os_atributos_da_tabela(): void
    {
        $atributos = PessoaMapper::fisica($this->pessoa('52998224725'));

        $this->assertSame(1001, $atributos['uau_codigo']);
        $this->assertSame('52998224725', $atributos['cpf']);
        $this->assertSame('Mariana Alves Ribeiro', $atributos['nome']);
        $this->assertSame('1988-05-17', $atributos['nascimento']);
        $this->assertSame('mariana.ribeiro@exemplo.com.br', $atributos['email']);
        $this->assertSame('62991234567', $atributos['telefone'], 'o celular');
        $this->assertSame('Rua T-37', $atributos['endereco']->logradouro, 'o endereço principal (tipo 0)');
        $this->assertSame('Apto 1203', $atributos['endereco']->complemento);
        $this->assertTrue($atributos['ativo']);
    }

    public function test_pessoa_juridica_vira_os_atributos_da_tabela_sem_regime_tributario(): void
    {
        $atributos = PessoaMapper::juridica($this->pessoa('27543198000106'));

        $this->assertSame([
            'uau_codigo', 'cnpj', 'razao_social', 'nome_fantasia', 'inscricao_municipal',
            'inscricao_estadual', 'email', 'telefone', 'endereco', 'ativo',
        ], array_keys($atributos));
        $this->assertArrayNotHasKey('regime_tributario', $atributos);
        $this->assertSame('27543198000106', $atributos['cnpj']);
        $this->assertSame('M A Ribeiro Serviços de Engenharia Ltda', $atributos['razao_social']);
        $this->assertSame('MAR Engenharia', $atributos['nome_fantasia']);
        $this->assertSame('6233334444', $atributos['telefone'], 'sem celular, o primeiro telefone');
        $this->assertSame('Av. 85', $atributos['endereco']->logradouro, 'sem principal, o primeiro endereço');

        $fillable = (new PessoaJuridica())->getFillable();
        $this->assertSame([], array_diff(array_keys($atributos), $fillable), 'todos os atributos são preenchíveis');
    }

    public function test_pessoa_inativa_sai_com_ativo_false(): void
    {
        $atributos = PessoaMapper::fisica($this->pessoa('74620153826'));

        $this->assertFalse($atributos['ativo']);
        $this->assertNull($atributos['telefone']);
        $this->assertNull($atributos['email']);
    }

    public function test_resumo_em_texto_para_o_log(): void
    {
        $this->assertSame(
            '3 criadas, 0 atualizadas, 1 inativada, 1 ignorada: 7/OB09',
            (string) new ResumoImportacao(criadas: 3, inativadas: 1, ignoradas: ['7/OB09']),
        );
        $this->assertSame(
            '0 criadas, 1 atualizada, 2 reativadas, 0 inativadas',
            (string) new ResumoImportacao(atualizadas: 1, reativadas: 2),
        );
    }

    /** @return list<EmpresaUau> */
    private function empresas(): array
    {
        return array_map(EmpresaUau::fromArray(...), RespostaUau::linhas($this->exemplo('empresas-ativas.json')));
    }

    private function pessoa(string $documento): PessoaUau
    {
        return PessoaUau::fromArray($this->exemplo("pessoas/{$documento}.json"));
    }

    private function exemplo(string $arquivo): mixed
    {
        return json_decode(file_get_contents(resource_path("fixtures/uau/{$arquivo}")), true, flags: JSON_THROW_ON_ERROR);
    }
}

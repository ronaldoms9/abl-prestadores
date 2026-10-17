<?php

namespace Tests\Unit;

use App\Actions\Pessoas\LinhaPlanilha;
use PHPUnit\Framework\TestCase;

class LinhaPlanilhaTest extends TestCase
{
    public function test_linha_vazia_e_ignorada(): void
    {
        $this->assertNull(LinhaPlanilha::deCelulas(2, ['cpf' => null, 'cnpj' => '  ']));
    }

    public function test_cpf_numerico_sem_o_zero_a_esquerda_e_completado(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => 3174682940, 'cnpj' => null]);

        $this->assertSame('03174682940', $linha->cpf);
        $this->assertTrue($linha->cpfValido);
        $this->assertNull($linha->mensagem());
    }

    public function test_cpf_em_texto_so_com_digitos_tambem_e_completado(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => '3174682940', 'cnpj' => null]);

        $this->assertSame('03174682940', $linha->cpf);
        $this->assertTrue($linha->cpfValido);
    }

    public function test_cnpj_numerico_em_float_vira_texto(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => null, 'cnpj' => 4252011000110.0]);

        $this->assertSame('04252011000110', $linha->cnpj);
        $this->assertTrue($linha->cnpjValido);
    }

    public function test_com_mascara_nao_completa_zeros(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => '174.682.940', 'cnpj' => null]);

        $this->assertFalse($linha->cpfValido);
        $this->assertSame('CPF deve ter 11 dígitos.', $linha->mensagem());
    }

    public function test_cnpj_em_notacao_cientifica_e_invalido(): void
    {
        $linha = LinhaPlanilha::deCelulas(5, ['cpf' => null, 'cnpj' => '1,12223E+13']);

        $this->assertSame(5, $linha->linha);
        $this->assertFalse($linha->cnpjValido);
        $this->assertSame('1,12223E+13', $linha->cnpj);
        $this->assertSame('CNPJ em notação científica; formate a coluna como texto.', $linha->mensagem());
    }

    public function test_cnpj_com_letras_tem_mensagem_propria(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => null, 'cnpj' => '12.ABC.345/01DE-35']);

        $this->assertFalse($linha->cnpjValido);
        $this->assertSame('CNPJ com letras ainda não é aceito.', $linha->mensagem());
    }

    public function test_digito_errado(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => '529.982.247-26', 'cnpj' => '11222333000181']);

        $this->assertFalse($linha->cpfValido);
        $this->assertSame('529.982.247-26', $linha->cpf);
        $this->assertTrue($linha->cnpjValido);
        $this->assertSame('CPF com dígito verificador inválido.', $linha->mensagem());
    }

    public function test_so_cpf_so_cnpj_e_os_dois(): void
    {
        $soCpf = LinhaPlanilha::deCelulas(2, ['cpf' => '52998224725', 'cnpj' => null]);
        $soCnpj = LinhaPlanilha::deCelulas(3, ['cpf' => '', 'cnpj' => '27.543.198/0001-06']);
        $osDois = LinhaPlanilha::deCelulas(4, ['cpf' => '52998224725', 'cnpj' => '27543198000106']);

        $this->assertSame(['52998224725', null], [$soCpf->cpf, $soCpf->cnpj]);
        $this->assertSame([null, '27543198000106'], [$soCnpj->cpf, $soCnpj->cnpj]);
        $this->assertTrue($osDois->cpfValido && $osDois->cnpjValido);
    }

    public function test_linha_sem_documento_mas_com_outra_coluna(): void
    {
        $linha = LinhaPlanilha::deCelulas(2, ['cpf' => null, 'cnpj' => null, 'nome' => 'Fulano']);

        $this->assertNotNull($linha);
        $this->assertSame('Informe o CPF ou o CNPJ.', $linha->mensagem());
    }
}

<?php

namespace Tests\Unit;

use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CpfCnpjTest extends TestCase
{
    public static function cpfs(): array
    {
        return [
            'válido sem máscara' => ['52998224725', true],
            'válido com máscara' => ['529.982.247-25', true],
            'zero à esquerda' => ['031.746.829-40', true],
            'dígito errado' => ['52998224726', false],
            'sequência repetida' => ['111.111.111-11', false],
            'curto' => ['5299822472', false],
            'longo' => ['529982247250', false],
            'vazio' => ['', false],
        ];
    }

    public static function cnpjs(): array
    {
        return [
            'válido sem máscara' => ['11222333000181', true],
            'válido com máscara' => ['11.222.333/0001-81', true],
            'zero à esquerda' => ['04.252.011/0001-10', true],
            'dígito errado' => ['11222333000182', false],
            'sequência repetida' => ['00000000000000', false],
            'curto' => ['1122233300018', false],
            'com letras' => ['12.ABC.345/01DE-35', false],
            'vazio' => ['', false],
        ];
    }

    #[DataProvider('cpfs')]
    public function test_cpf(string $cpf, bool $esperado): void
    {
        $this->assertSame($esperado, Cpf::valido($cpf));
        $this->assertSame($esperado, Validator::make(['cpf' => $cpf], ['cpf' => ['required', new Cpf]])->passes());
    }

    #[DataProvider('cnpjs')]
    public function test_cnpj(string $cnpj, bool $esperado): void
    {
        $this->assertSame($esperado, Cnpj::valido($cnpj));
    }

    public function test_rule_de_formulario_traz_a_mensagem(): void
    {
        $validador = Validator::make(['cnpj' => '11222333000182'], ['cnpj' => [new Cnpj]]);

        $this->assertTrue($validador->fails());
        $this->assertSame('O cnpj não é um CNPJ válido.', $validador->errors()->first('cnpj'));
    }

    public function test_helper_validacpf_continua_respondendo_igual(): void
    {
        $this->assertTrue(validaCPF('529.982.247-25'));
        $this->assertFalse(validaCPF('529.982.247-26'));
        $this->assertFalse(validaCPF('111.111.111-11'));
        $this->assertFalse(validaCPF('alguem@example.com'));
        $this->assertFalse(validaCPF(null));
    }
}

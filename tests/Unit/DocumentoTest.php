<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public function test_mascara_removida(): void
    {
        $this->assertSame('12345678901', Documento::digitos('123.456.789-01'));
        $this->assertSame('12345678000199', Documento::digitos('12.345.678/0001-99'));
    }

    public function test_zero_a_esquerda_mantido(): void
    {
        $this->assertSame('01234567890', Documento::digitos('012.345.678-90'));
        $this->assertSame('01234567000189', Documento::digitos('01.234.567/0001-89'));
    }

    public function test_vazio_e_null_retornam_null(): void
    {
        $this->assertNull(Documento::digitos(null));
        $this->assertNull(Documento::digitos(''));
        $this->assertNull(Documento::digitos('   '));
        $this->assertNull(Documento::digitos('.-/'));
    }

    public function test_formatacao_de_cpf_e_cnpj(): void
    {
        $this->assertSame('012.345.678-90', Documento::formatar('01234567890'));
        $this->assertSame('01.234.567/0001-89', Documento::formatar('01234567000189'));

        // Se já vier formatado ou com máscara
        $this->assertSame('012.345.678-90', Documento::formatar('012.345.678-90'));

        // Outro tamanho volta como está
        $this->assertSame('123', Documento::formatar('123'));
        $this->assertSame('123456789012345', Documento::formatar('123456789012345'));
        $this->assertNull(Documento::formatar(null));
        $this->assertSame('', Documento::formatar(''));
    }
}

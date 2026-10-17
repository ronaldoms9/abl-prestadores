<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Hex2RgbTest extends TestCase
{
    public function test_converte_hexadecimal_de_seis_digitos(): void
    {
        $this->assertSame('13, 110, 253', hex2rgb('#0d6efd'));
        $this->assertSame('255, 255, 255', hex2rgb('FFFFFF'));
    }

    public function test_converte_hexadecimal_de_tres_digitos(): void
    {
        $this->assertSame('0, 102, 255', hex2rgb('#06f'));
    }

    public function test_cor_invalida_retorna_null(): void
    {
        $this->assertNull(hex2rgb(''));
        $this->assertNull(hex2rgb(null));
        $this->assertNull(hex2rgb('#12345'));
        $this->assertNull(hex2rgb('azul'));
    }
}

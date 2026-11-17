<?php

namespace Tests\Unit;

use App\Enums\FormaReajuste;
use App\Support\Dinheiro;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public function test_converte_formatos_para_centavos(): void
    {
        $this->assertSame(500050, Dinheiro::centavos('5000.50'));
        $this->assertSame(500050, Dinheiro::centavos('5.000,50'));
        $this->assertSame(500050, Dinheiro::centavos(5000.5));
        $this->assertSame(500000, Dinheiro::centavos(5000));
        $this->assertSame(1, Dinheiro::centavos('0.01'));
        $this->assertSame(1999, Dinheiro::centavos('19,99'), '19.99 em ponto flutuante não pode virar 1998');
    }

    public function test_formata_para_gravar_e_para_exibir(): void
    {
        $this->assertSame('5000.50', Dinheiro::decimal(500050));
        $this->assertSame('R$ 5.000,50', Dinheiro::formatar(500050));
        $this->assertSame('-R$ 10,00', Dinheiro::formatar(-1000));
    }

    public function test_reajuste_percentual_arredonda_meio_para_cima_em_duas_casas(): void
    {
        $this->assertSame(525000, Dinheiro::reajustar(500000, FormaReajuste::Percentual, '5'));
        // 3.333,33 + 5% = 3.499,9965 -> 3.500,00
        $this->assertSame(350000, Dinheiro::reajustar(333333, FormaReajuste::Percentual, '5'));
        // 100,10 + 0,5% = 100,6005 -> 100,60
        $this->assertSame(10060, Dinheiro::reajustar(10010, FormaReajuste::Percentual, '0.5'));
        // 0,10 + 5% = 0,105 -> meio para cima = 0,11
        $this->assertSame(11, Dinheiro::reajustar(10, FormaReajuste::Percentual, '5'));
        // O percentual vale em 2 casas: 0,1051% é lido como 0,11%.
        $this->assertSame(500550, Dinheiro::reajustar(500000, FormaReajuste::Percentual, '0.1051'));
        $this->assertSame(500500, Dinheiro::reajustar(500000, FormaReajuste::Percentual, '0.1'));
    }

    public function test_reajuste_em_valor_soma_ao_atual(): void
    {
        $this->assertSame(520000, Dinheiro::reajustar(500000, FormaReajuste::Valor, '200'));
        $this->assertSame(500050, Dinheiro::reajustar(500000, FormaReajuste::Valor, '0.50'));
    }
}

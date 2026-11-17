<?php

namespace Tests\Unit\Contratos;

use App\Support\Contratos\Extenso;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExtensoTest extends TestCase
{
    public static function valores(): array
    {
        return [
            'zero' => [0, 'zero reais'],
            'um centavo' => [1, 'um centavo'],
            'dois centavos' => [2, 'dois centavos'],
            'um real' => [100, 'um real'],
            'real e centavo' => [101, 'um real e um centavo'],
            'dezenove' => [1900, 'dezenove reais'],
            'vinte e um' => [2100, 'vinte e um reais'],
            'cem' => [10000, 'cem reais'],
            'cento e um' => [10100, 'cento e um reais'],
            'duzentos e cinquenta' => [25000, 'duzentos e cinquenta reais'],
            'mil' => [100000, 'mil reais'],
            'mil e quinhentos' => [150000, 'mil e quinhentos reais'],
            'mil e cinquenta' => [105000, 'mil e cinquenta reais'],
            'mil duzentos e trinta e quatro' => [123456, 'mil duzentos e trinta e quatro reais e cinquenta e seis centavos'],
            'dois mil' => [200000, 'dois mil reais'],
            'cinco mil duzentos e cinquenta' => [525000, 'cinco mil duzentos e cinquenta reais'],
            'dois mil e vinte' => [202000, 'dois mil e vinte reais'],
            'dez mil' => [1000000, 'dez mil reais'],
            'cem mil' => [10000000, 'cem mil reais'],
            'um milhão' => [100000000, 'um milhão de reais'],
            'dois milhões' => [200000000, 'dois milhões de reais'],
            'um milhão e cem' => [100010000, 'um milhão e cem reais'],
            'um milhão e doze mil' => [101200000, 'um milhão e doze mil reais'],
            'um milhão e duzentos mil' => [120000000, 'um milhão e duzentos mil reais'],
            'dois milhões e meio' => [250000000, 'dois milhões e quinhentos mil reais'],
            'milhão com mil e resto' => [123456700, 'um milhão duzentos e trinta e quatro mil quinhentos e sessenta e sete reais'],
            'um bilhão' => [100000000000, 'um bilhão de reais'],
            'centavos com reais' => [150050, 'mil e quinhentos reais e cinquenta centavos'],
            'negativo' => [-250, 'menos dois reais e cinquenta centavos'],
        ];
    }

    #[DataProvider('valores')]
    public function test_valor_por_extenso(int $centavos, string $esperado): void
    {
        $this->assertSame($esperado, Extenso::moeda($centavos));
    }

    public function test_data_por_extenso(): void
    {
        $this->assertSame('1 de março de 2026', Extenso::data(Carbon::parse('2026-03-01')));
        $this->assertSame('17 de novembro de 2026', Extenso::data(Carbon::parse('2026-11-17')));
    }
}

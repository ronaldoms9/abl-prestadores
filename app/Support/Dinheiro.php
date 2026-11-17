<?php

namespace App\Support;

use App\Enums\FormaReajuste;
use InvalidArgumentException;

/**
 * Valores em reais como centavos inteiros, para não acumular erro de ponto
 * flutuante nos reajustes. Arredonda meio para cima, em 2 casas.
 */
class Dinheiro
{
    /**
     * "5.000,50" (com vírgula, formato brasileiro), "5000.50" (ponto é o decimal)
     * ou 5000.5 viram 500050. Os formulários enviam number com ponto decimal.
     */
    public static function centavos(string|int|float $valor): int
    {
        if (is_string($valor)) {
            $valor = trim($valor);
            // Formato brasileiro: ponto separa milhar e vírgula, o decimal.
            if (preg_match('/,\d{1,2}$/', $valor)) {
                $valor = str_replace(',', '.', str_replace('.', '', $valor));
            }
            if (! is_numeric($valor)) {
                throw new InvalidArgumentException("Valor inválido: {$valor}");
            }
        }

        return (int) round(((float) $valor) * 100, 0, PHP_ROUND_HALF_UP);
    }

    /** 500050 vira "5000.50" (para gravar) */
    public static function decimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    /** 500050 vira "R$ 5.000,50". */
    public static function formatar(int $centavos): string
    {
        return ($centavos < 0 ? '-' : '').'R$ '.number_format(abs($centavos) / 100, 2, ',', '.');
    }

    /**
     * Valor depois do reajuste. Percentual: acréscimo percentual sobre o atual
     * (5 = 5%). Valor: acréscimo em reais sobre o atual.
     */
    public static function reajustar(int $atual, FormaReajuste $forma, string|int|float $valor): int
    {
        if ($forma === FormaReajuste::Valor) {
            return $atual + self::centavos($valor);
        }

        $pontosBase = self::centavos($valor); // 5,25% = 525 centésimos de ponto percentual
        $bruto = $atual * (10000 + $pontosBase);

        return intdiv($bruto + 5000, 10000);
    }
}

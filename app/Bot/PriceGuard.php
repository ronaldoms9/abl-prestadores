<?php

namespace App\Bot;

use App\Models\BotConfig;

/**
 * Confere, no nosso código, que o modelo não inventou preço.
 *
 * Só olha números marcados como dinheiro ("R$ 80", "80 reais"): sem isso um
 * "atendemos sábado das 9h às 13h" seria bloqueado por conter números.
 */
class PriceGuard
{
    private const TOLERANCIA = 0.005;

    public function verificar(string $resposta, BotConfig $config): PriceVerdict
    {
        $citados = $this->precosCitados($resposta);

        if ($citados === []) {
            return PriceVerdict::liberado();
        }

        $permitidos = $config->precosPermitidos();

        $inventados = array_values(array_filter(
            $citados,
            fn (float $preco) => ! $this->estaNaLista($preco, $permitidos)
        ));

        return $inventados === []
            ? PriceVerdict::liberado()
            : PriceVerdict::bloqueado($inventados);
    }

    /** @return array<int, float> */
    public function precosCitados(string $texto): array
    {
        $precos = [];

        // R$ 1.234,56 | R$ 80 | 80,00 reais | 1.234 reais
        preg_match_all('/(?:R\$\s*([\d.,]+))|([\d.,]+)\s*(?:reais|real)\b/iu', $texto, $casos, PREG_SET_ORDER);

        foreach ($casos as $caso) {
            $cru = $caso[1] ?? '';

            if ($cru === '') {
                $cru = $caso[2] ?? '';
            }

            $valor = $this->paraFloat($cru);

            if ($valor !== null) {
                $precos[] = $valor;
            }
        }

        return $precos;
    }

    private function paraFloat(string $cru): ?float
    {
        $cru = trim($cru, " \t\n\r\0\x0B.,");

        if ($cru === '') {
            return null;
        }

        // Formato brasileiro: ponto é milhar, vírgula é decimal.
        $normalizado = str_contains($cru, ',')
            ? str_replace(',', '.', str_replace('.', '', $cru))
            : str_replace('.', '', $cru);

        return is_numeric($normalizado) ? (float) $normalizado : null;
    }

    /** @param  array<int, float>  $permitidos */
    private function estaNaLista(float $preco, array $permitidos): bool
    {
        foreach ($permitidos as $permitido) {
            if (abs($preco - $permitido) < self::TOLERANCIA) {
                return true;
            }
        }

        return false;
    }
}

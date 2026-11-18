<?php

namespace App\Services\Relatorios;

use App\Support\Dinheiro;

/** Formatação comum dos relatórios (texto pronto para a tela, o Excel e o PDF). */
final class Formato
{
    /** Decimal em reais ("1234.5") para "R$ 1.234,50". */
    public static function reais(string|int|float|null $valor): string
    {
        return Dinheiro::formatar(Dinheiro::centavos((string) ($valor ?? 0)));
    }

    public static function mes(string $anoMes): string
    {
        return substr($anoMes, 5, 2).'/'.substr($anoMes, 0, 4);
    }
}

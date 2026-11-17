<?php

namespace App\Enums;

enum FormaReajuste: string
{
    case Percentual = 'percentual';
    case Valor = 'valor';

    public function rotulo(): string
    {
        return match ($this) {
            self::Percentual => 'Percentual',
            self::Valor => 'Valor fixo',
        };
    }
}

<?php

namespace App\Enums;

enum TipoAjuste: string
{
    case Credito = 'credito';
    case Debito = 'debito';

    public function rotulo(): string
    {
        return match ($this) {
            self::Credito => 'Crédito',
            self::Debito => 'Débito',
        };
    }
}

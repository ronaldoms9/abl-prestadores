<?php

namespace App\Enums;

enum TipoReajuste: string
{
    case Individual = 'individual';
    case Lote = 'lote';

    public function rotulo(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Lote => 'Em lote',
        };
    }
}

<?php

namespace App\Enums;

enum TipoOcorrencia: string
{
    case Falta = 'falta';
    case Afastamento = 'afastamento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Falta => 'Falta',
            self::Afastamento => 'Afastamento',
        };
    }
}

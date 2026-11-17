<?php

namespace App\Enums;

enum TipoContrato: string
{
    case Contrato = 'contrato';
    case Aditivo = 'aditivo';
    case Distrato = 'distrato';

    public function rotulo(): string
    {
        return match ($this) {
            self::Contrato => 'Contrato',
            self::Aditivo => 'Aditivo',
            self::Distrato => 'Distrato',
        };
    }
}

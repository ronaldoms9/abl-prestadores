<?php

namespace App\Enums;

enum OrigemSincronizacao: string
{
    case Agendada = 'agendada';
    case Manual = 'manual';

    public function rotulo(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Manual => 'Manual',
        };
    }
}

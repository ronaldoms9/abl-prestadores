<?php

namespace App\Enums;

enum AbrangenciaFeriado: string
{
    case Nacional = 'nacional';
    case Estadual = 'estadual';
    case Municipal = 'municipal';

    public function rotulo(): string
    {
        return match ($this) {
            self::Nacional => 'Nacional',
            self::Estadual => 'Estadual',
            self::Municipal => 'Municipal',
        };
    }
}

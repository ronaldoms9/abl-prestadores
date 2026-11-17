<?php

namespace App\Enums;

enum DecisaoFerias: string
{
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
    /** Nível sem aprovador elegível: registrado e pulado (escalada). */
    case Pulada = 'pulada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aprovada => 'Aprovada',
            self::Recusada => 'Recusada',
            self::Pulada => 'Nível pulado',
        };
    }
}

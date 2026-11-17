<?php

namespace App\Enums;

enum StatusSolicitacaoFerias: string
{
    case Pendente = 'pendente';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Aprovada => 'Aprovada',
            self::Recusada => 'Recusada',
            self::Cancelada => 'Cancelada',
        };
    }
}

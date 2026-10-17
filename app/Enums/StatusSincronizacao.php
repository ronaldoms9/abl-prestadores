<?php

namespace App\Enums;

enum StatusSincronizacao: string
{
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Falhou = 'falhou';

    public function rotulo(): string
    {
        return match ($this) {
            self::EmAndamento => 'Em andamento',
            self::Concluida => 'Concluída',
            self::Falhou => 'Falhou',
        };
    }
}

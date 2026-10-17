<?php

namespace App\Enums;

enum StatusImportacao: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluida = 'concluida';
    case Falhou = 'falhou';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processando => 'Processando',
            self::Concluida => 'Concluída',
            self::Falhou => 'Falhou',
        };
    }
}

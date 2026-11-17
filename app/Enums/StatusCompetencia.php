<?php

namespace App\Enums;

enum StatusCompetencia: string
{
    case Aberta = 'aberta';
    case EmConferencia = 'em_conferencia';
    case Aprovada = 'aprovada';
    case Fechada = 'fechada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::EmConferencia => 'Em conferência',
            self::Aprovada => 'Aprovada',
            self::Fechada => 'Fechada',
        };
    }

    /** Competência em que os valores não mudam mais (lançamentos e recálculo recusados). */
    public function imutavel(): bool
    {
        return in_array($this, [self::Aprovada, self::Fechada], true);
    }
}

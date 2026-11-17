<?php

namespace App\Enums;

enum StatusFechamento: string
{
    case Calculado = 'calculado';
    /** Competência aprovada: o fechamento espera a nota fiscal (E5). */
    case AguardandoNota = 'aguardando_nota';

    public function rotulo(): string
    {
        return match ($this) {
            self::Calculado => 'Calculado',
            self::AguardandoNota => 'Aguardando nota',
        };
    }
}

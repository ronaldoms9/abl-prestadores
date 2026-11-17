<?php

namespace App\Enums;

enum TipoCalculoBeneficio: string
{
    case FixoMensal = 'fixo_mensal';
    case PorDiaTrabalhado = 'por_dia_trabalhado';

    public function rotulo(): string
    {
        return match ($this) {
            self::FixoMensal => 'Fixo mensal',
            self::PorDiaTrabalhado => 'Por dia trabalhado',
        };
    }
}

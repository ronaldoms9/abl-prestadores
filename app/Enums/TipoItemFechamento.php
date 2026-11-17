<?php

namespace App\Enums;

enum TipoItemFechamento: string
{
    case Remuneracao = 'remuneracao';
    case Beneficio = 'beneficio';
    case Ajuste = 'ajuste';
    case Desconto = 'desconto';

    public function rotulo(): string
    {
        return match ($this) {
            self::Remuneracao => 'Remuneração',
            self::Beneficio => 'Benefício',
            self::Ajuste => 'Ajuste',
            self::Desconto => 'Desconto',
        };
    }
}

<?php

namespace App\Enums;

/** Por que um aditivo foi gerado; escolhe o modelo e as variáveis do aditivo (E3, fatias 4 e 9). */
enum MotivoAditivo: string
{
    case Prorrogacao = 'prorrogacao';
    case Reajuste = 'reajuste';
    case MudancaCargo = 'mudanca_cargo';
    case Beneficios = 'beneficios';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Prorrogacao => 'Prorrogação',
            self::Reajuste => 'Reajuste de remuneração',
            self::MudancaCargo => 'Mudança de cargo',
            self::Beneficios => 'Alteração de benefícios',
            self::Outro => 'Outro',
        };
    }
}

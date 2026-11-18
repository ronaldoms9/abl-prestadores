<?php

namespace App\Enums;

enum StatusTituloPagar: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Erro = 'erro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Enviado => 'Enviado ao UAU',
            self::Erro => 'Erro',
        };
    }
}

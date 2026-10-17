<?php

namespace App\Enums;

enum StatusIntegracao: string
{
    case Sucesso = 'sucesso';
    case Erro = 'erro';
    case Pendente = 'pendente';

    public function rotulo(): string
    {
        return match ($this) {
            self::Sucesso => 'Sucesso',
            self::Erro => 'Erro',
            self::Pendente => 'Pendente',
        };
    }
}

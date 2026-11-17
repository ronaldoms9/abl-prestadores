<?php

namespace App\Enums;

enum TipoChavePix: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Email = 'email';
    case Telefone = 'telefone';
    case Aleatoria = 'aleatoria';

    public function rotulo(): string
    {
        return match ($this) {
            self::Cpf => 'CPF',
            self::Cnpj => 'CNPJ',
            self::Email => 'E-mail',
            self::Telefone => 'Telefone',
            self::Aleatoria => 'Chave aleatória',
        };
    }
}

<?php

namespace App\Enums;

enum TipoContaBancaria: string
{
    case Corrente = 'corrente';
    case Poupanca = 'poupanca';
    case Pagamento = 'pagamento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Corrente => 'Conta corrente',
            self::Poupanca => 'Poupança',
            self::Pagamento => 'Conta de pagamento',
        };
    }
}

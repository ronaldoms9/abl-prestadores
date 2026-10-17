<?php

namespace App\Integrations\Uau\Data;

/** Campo "tipo" da pessoa no UAU. */
enum TipoPessoaUau: int
{
    case Fisica = 0;
    case Juridica = 1;

    public function rotulo(): string
    {
        return $this === self::Fisica ? 'PF' : 'PJ';
    }

    /** Tamanho do documento: 11 (CPF) ou 14 (CNPJ). */
    public function digitos(): int
    {
        return $this === self::Fisica ? 11 : 14;
    }
}

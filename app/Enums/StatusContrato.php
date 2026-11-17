<?php

namespace App\Enums;

enum StatusContrato: string
{
    case Rascunho = 'rascunho';
    case Gerado = 'gerado';
    case Assinado = 'assinado';
    case Encerrado = 'encerrado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Gerado => 'Gerado',
            self::Assinado => 'Assinado',
            self::Encerrado => 'Encerrado',
        };
    }

    /** Contrato gerado é imutável: só o rascunho pode ser cancelado ou refeito. */
    public function imutavel(): bool
    {
        return $this !== self::Rascunho;
    }
}

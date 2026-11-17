<?php

namespace App\Enums;

enum StatusColaborador: string
{
    case PreAdmissao = 'pre_admissao';
    case Ativo = 'ativo';
    case Desligado = 'desligado';

    public function rotulo(): string
    {
        return match ($this) {
            self::PreAdmissao => 'Pré-admissão',
            self::Ativo => 'Ativo',
            self::Desligado => 'Desligado',
        };
    }

    /** Status em que o colaborador ocupa a PF: só um por vez. */
    public static function abertos(): array
    {
        return [self::PreAdmissao->value, self::Ativo->value];
    }
}

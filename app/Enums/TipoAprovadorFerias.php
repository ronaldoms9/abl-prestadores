<?php

namespace App\Enums;

enum TipoAprovadorFerias: string
{
    case GestorEquipe = 'gestor_equipe';
    case Permissao = 'permissao';

    public function rotulo(): string
    {
        return match ($this) {
            self::GestorEquipe => 'Gestor da equipe',
            self::Permissao => 'Quem tem a permissão',
        };
    }
}

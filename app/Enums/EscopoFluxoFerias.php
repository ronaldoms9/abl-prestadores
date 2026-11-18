<?php

namespace App\Enums;

/** A quem um fluxo de aprovação de férias se aplica, do mais específico ao mais geral (E4, fatia 9). */
enum EscopoFluxoFerias: string
{
    case Colaborador = 'colaborador';
    case EquipeCargo = 'equipe_cargo';
    case Equipe = 'equipe';
    case Cargo = 'cargo';
    case Padrao = 'padrao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Colaborador => 'Um colaborador',
            self::EquipeCargo => 'Uma função dentro de uma equipe',
            self::Equipe => 'Uma equipe',
            self::Cargo => 'Uma função, em qualquer equipe',
            self::Padrao => 'Padrão (quem não tem fluxo próprio)',
        };
    }

    /** Quanto maior, mais específico: o fluxo de maior especificidade que casa é o que vale. */
    public function especificidade(): int
    {
        return match ($this) {
            self::Colaborador => 5,
            self::EquipeCargo => 4,
            self::Equipe => 3,
            self::Cargo => 2,
            self::Padrao => 1,
        };
    }
}

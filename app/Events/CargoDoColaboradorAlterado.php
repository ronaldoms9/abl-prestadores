<?php

namespace App\Events;

use App\Models\Colaborador;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** O cargo de um colaborador já admitido mudou. A E3 cria o rascunho do aditivo de mudança de cargo. */
class CargoDoColaboradorAlterado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Colaborador $colaborador,
        public readonly ?int $cargoAnteriorId,
        public readonly ?int $cargoNovoId,
    ) {
    }
}

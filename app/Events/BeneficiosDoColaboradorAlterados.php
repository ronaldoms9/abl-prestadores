<?php

namespace App\Events;

use App\Models\Colaborador;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Um benefício do colaborador foi vinculado ou encerrado depois da admissão. A E3 cria o rascunho do aditivo de benefícios. */
class BeneficiosDoColaboradorAlterados
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Colaborador $colaborador,
        public readonly string $descricao,
        public readonly string $inicio,
    ) {
    }
}

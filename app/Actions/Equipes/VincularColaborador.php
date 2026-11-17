<?php

namespace App\Actions\Equipes;

use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Equipe;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Põe o colaborador numa equipe a partir de uma data. Ele pode estar em várias
 * equipes ou em nenhuma, mas não duas vezes abertas na mesma.
 */
class VincularColaborador
{
    public function executar(Colaborador $colaborador, Equipe $equipe, ?CarbonInterface $inicio = null): ColaboradorEquipe
    {
        if (! $equipe->ativo) {
            throw ValidationException::withMessages(['equipe_id' => 'A equipe está inativa.']);
        }

        if (ColaboradorEquipe::where('colaborador_id', $colaborador->id)->where('equipe_id', $equipe->id)->vigentes()->exists()) {
            throw ValidationException::withMessages(['equipe_id' => 'O colaborador já está nesta equipe.']);
        }

        return ColaboradorEquipe::create([
            'colaborador_id' => $colaborador->id,
            'equipe_id' => $equipe->id,
            'inicio' => ($inicio ?? now())->copy()->startOfDay(),
        ]);
    }
}

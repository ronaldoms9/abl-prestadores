<?php

namespace App\Actions\Equipes;

use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Põe um usuário como gestor da equipe a partir de uma data. Só entra quem
 * está ativo e tem a permissão equipe-gerir; o mesmo usuário não fica duas
 * vezes aberto na mesma equipe.
 */
class AdicionarGestor
{
    public function executar(Equipe $equipe, User $usuario, ?CarbonInterface $inicio = null): EquipeGestor
    {
        if (! $usuario->ativo || ! $usuario->can('equipe-gerir')) {
            throw ValidationException::withMessages(['user_id' => 'Só usuários ativos com a permissão de gerir equipes podem ser gestores.']);
        }

        if (EquipeGestor::where('equipe_id', $equipe->id)->where('user_id', $usuario->id)->vigentes()->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Este usuário já é gestor desta equipe.']);
        }

        return EquipeGestor::create([
            'equipe_id' => $equipe->id,
            'user_id' => $usuario->id,
            'inicio' => ($inicio ?? now())->copy()->startOfDay(),
        ]);
    }
}

<?php

namespace App\Actions\Equipes;

use App\Models\ColaboradorEquipe;
use App\Models\EquipeGestor;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Encerra um vínculo de gestor ou de colaborador com a equipe, na data informada (fim exclusivo). */
class EncerrarVinculo
{
    public function executar(EquipeGestor|ColaboradorEquipe $vinculo, ?CarbonInterface $fim = null): void
    {
        $fim = ($fim ?? now())->copy()->startOfDay();

        if ($vinculo->fim !== null) {
            throw ValidationException::withMessages(['fim' => 'Este vínculo já foi encerrado.']);
        }

        if ($fim->lt($vinculo->inicio)) {
            throw ValidationException::withMessages(['fim' => 'A data de fim não pode ser anterior ao início ('.$vinculo->inicio->format('d/m/Y').').']);
        }

        $vinculo->update(['fim' => $fim]);
    }
}

<?php

namespace App\Support\Competencias;

use App\Enums\StatusCompetencia;
use App\Models\Competencia;
use Carbon\CarbonInterface;

/** Mês fechado = existe competência dele já aprovada (ou fechada). */
class CompetenciasAprovadas implements CompetenciaFechada
{
    public function fechada(CarbonInterface $data): bool
    {
        return Competencia::query()
            ->where('ano_mes', $data->format('Y-m'))
            ->whereIn('status', array_map(fn (StatusCompetencia $s) => $s->value, array_filter(StatusCompetencia::cases(), fn (StatusCompetencia $s) => $s->imutavel())))
            ->exists();
    }
}

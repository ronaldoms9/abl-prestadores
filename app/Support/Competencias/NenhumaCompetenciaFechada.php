<?php

namespace App\Support\Competencias;

use Carbon\CarbonInterface;

class NenhumaCompetenciaFechada implements CompetenciaFechada
{
    public function fechada(CarbonInterface $data): bool
    {
        return false;
    }
}

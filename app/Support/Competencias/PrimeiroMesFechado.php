<?php

namespace App\Support\Competencias;

use Carbon\CarbonInterface;

/** Primeiro mês de um período (datas inclusive) que já foi fechado, ou null. */
class PrimeiroMesFechado
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    public function em(CarbonInterface $inicio, CarbonInterface $fim): ?CarbonInterface
    {
        for ($mes = $inicio->copy()->startOfMonth(); $mes->lte($fim); $mes->addMonth()) {
            $dia = $mes->copy()->max($inicio);

            if ($this->competencias->fechada($dia)) {
                return $dia;
            }
        }

        return null;
    }
}

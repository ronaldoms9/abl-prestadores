<?php

namespace App\Support\Competencias;

use Carbon\CarbonInterface;

/**
 * Diz se o mês de uma data já foi fechado. A Entrega 4 troca a implementação
 * padrão pela que consulta as competências aprovadas; até lá nenhuma está fechada.
 */
interface CompetenciaFechada
{
    public function fechada(CarbonInterface $data): bool;
}

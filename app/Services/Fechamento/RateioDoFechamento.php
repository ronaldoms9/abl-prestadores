<?php

namespace App\Services\Fechamento;

use App\Models\Colaborador;
use App\Models\ColaboradorObra;
use Carbon\CarbonInterface;

/**
 * Reparte o que o colaborador recebe no período entre as obras em que atuou (E4, fatia 10).
 * Cada dia corrido do período pesa o rateio vigente nele; dia sem rateio cadastrado vale a
 * obra do colaborador com 100%. Os valores são divididos em centavos e a sobra vai à obra
 * de maior parcela (empate: menor id), para a soma bater exatamente com o fechamento.
 */
class RateioDoFechamento
{
    /**
     * @return list<array{obra_id: int|null, percentual: string, remuneracao: int, beneficios: int, ajustes: int, liquido: int}>
     */
    public function calcular(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim, int $remuneracao, int $beneficios, int $ajustes): array
    {
        $linhas = ColaboradorObra::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('inicio', '<=', $fim->toDateString())
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>', $inicio->toDateString()))
            ->get();

        $pesos = [];   // obra_id|'' => pontos-base somados nos dias
        $dias = 0;

        for ($dia = $inicio->copy()->startOfDay(); $dia->lte($fim); $dia->addDay()) {
            $dias++;
            $hoje = $linhas->filter(fn (ColaboradorObra $l) => $l->inicio->lte($dia) && ($l->fim === null || $l->fim->gt($dia)));

            if ($hoje->isEmpty()) {
                $pesos[(string) $colaborador->obra_id] = ($pesos[(string) $colaborador->obra_id] ?? 0) + 10000;

                continue;
            }

            foreach ($hoje as $l) {
                $pesos[(string) $l->obra_id] = ($pesos[(string) $l->obra_id] ?? 0) + $l->pontosBase();
            }
        }

        if ($dias === 0) {
            return [];
        }

        // Maior parcela primeiro; a "sem obra" (chave vazia) por último no empate.
        uksort($pesos, fn ($a, $b) => [$pesos[$b], $a === '' ? PHP_INT_MAX : (int) $a] <=> [$pesos[$a], $b === '' ? PHP_INT_MAX : (int) $b]);

        $total = $dias * 10000;
        $remuneracoes = $this->repartir($remuneracao, $pesos, $total);
        $beneficiosPorObra = $this->repartir($beneficios, $pesos, $total);
        $ajustesPorObra = $this->repartir($ajustes, $pesos, $total);

        $resultado = [];
        foreach ($pesos as $obra => $peso) {
            $resultado[] = [
                'obra_id' => $obra === '' ? null : (int) $obra,
                'percentual' => number_format($peso / ($dias * 100), 4, '.', ''),
                'remuneracao' => $remuneracoes[$obra],
                'beneficios' => $beneficiosPorObra[$obra],
                'ajustes' => $ajustesPorObra[$obra],
                'liquido' => $remuneracoes[$obra] + $beneficiosPorObra[$obra] + $ajustesPorObra[$obra],
            ];
        }

        return $resultado;
    }

    /**
     * @param  array<string, int>  $pesos  já na ordem de maior parcela
     * @return array<string, int>
     */
    private function repartir(int $valor, array $pesos, int $total): array
    {
        $partes = [];
        foreach ($pesos as $obra => $peso) {
            $partes[$obra] = $this->dividirArredondando($valor * $peso, $total);
        }

        $sobra = $valor - array_sum($partes);
        if ($sobra !== 0) {
            $partes[array_key_first($partes)] += $sobra;
        }

        return $partes;
    }

    private function dividirArredondando(int $a, int $b): int
    {
        $q = intdiv(abs($a) * 2 + $b, $b * 2);

        return $a < 0 ? -$q : $q;
    }
}

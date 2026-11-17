<?php

namespace App\Actions\Ferias;

use App\Enums\StatusColaborador;
use App\Models\FeriasPeriodo;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Períodos com saldo e perto do limite de gozo, para o alerta diário. Entram
 * os de colaborador ativo cujo limite está a até $dias dias, ou já passou, com
 * saldo maior que zero. O limite é o último dia para gozar (inclusive).
 */
class FeriasAVencer
{
    /**
     * @return array{vencidos: Collection<int, FeriasPeriodo>, a_vencer: Collection<int, FeriasPeriodo>}
     */
    public function consultar(int $dias, CarbonInterface $hoje): array
    {
        $dia = $hoje->copy()->startOfDay();

        $periodos = FeriasPeriodo::query()
            ->whereColumn('dias_utilizados', '<', 'dias_direito')
            ->whereDate('limite_gozo', '<=', $dia->copy()->addDays($dias)->toDateString())
            ->whereHas('colaborador', fn ($q) => $q->where('status', StatusColaborador::Ativo->value))
            ->with('colaborador.pessoaFisica')
            ->orderBy('limite_gozo')
            ->get();

        return [
            'vencidos' => $periodos->filter(fn (FeriasPeriodo $p) => $p->limite_gozo->lt($dia))->values(),
            'a_vencer' => $periodos->filter(fn (FeriasPeriodo $p) => $p->limite_gozo->gte($dia))->values(),
        ];
    }
}

<?php

namespace App\Actions\Beneficios;

use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Dá ao colaborador os benefícios padrão do cargo, a partir de uma data
 * (a admissão). Pula benefício inativo e benefício que ele já tem no dia,
 * então pode rodar de novo sem duplicar.
 */
class AplicarBeneficiosPadrao
{
    public function __construct(private readonly VincularBeneficio $vincular)
    {
    }

    /** @return Collection<int, ColaboradorBeneficio> Os vínculos criados agora. */
    public function executar(Colaborador $colaborador, CarbonInterface $inicio): Collection
    {
        if ($colaborador->cargo_id === null) {
            return collect();
        }

        $jaTem = ColaboradorBeneficio::query()
            ->where('colaborador_id', $colaborador->id)
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>', $inicio->toDateString()))
            ->pluck('beneficio_id');

        $padrao = $colaborador->cargo()->first()
            ->beneficiosPadrao()
            ->with('beneficio')
            ->get()
            ->filter(fn ($item) => $item->beneficio->ativo && ! $jaTem->contains($item->beneficio_id));

        return $padrao->map(fn ($item) => $this->vincular->executar($colaborador, $item->beneficio, $inicio))->values();
    }
}

<?php

namespace App\Actions\Beneficios;

use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Valor efetivo de um benefício de um colaborador em um dia: o valor
 * personalizado do vínculo, ou, sem ele, o da tabela vigente naquele dia.
 * É o que o fechamento mensal (E4) soma, dia a dia. Em centavos.
 */
class BeneficioVigente
{
    /** Null quando o colaborador não tem o benefício no dia, ou a tabela não tem valor no dia. */
    public function valorEm(Colaborador $colaborador, Beneficio $beneficio, CarbonInterface $data): ?int
    {
        $vinculo = ColaboradorBeneficio::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('beneficio_id', $beneficio->id)
            ->vigentesEm($data)
            ->first();

        if ($vinculo === null) {
            return null;
        }

        if ($vinculo->valor_personalizado !== null) {
            return Dinheiro::centavos($vinculo->valor_personalizado);
        }

        $tabela = BeneficioValor::query()->where('beneficio_id', $beneficio->id)->vigentesEm($data)->first();

        return $tabela === null ? null : Dinheiro::centavos($tabela->valor);
    }

    /**
     * Todos os benefícios do colaborador no dia, com o valor efetivo e a origem.
     *
     * @return Collection<int, array{vinculo: ColaboradorBeneficio, beneficio: Beneficio, valor: ?int, personalizado: bool}>
     */
    public function listaEm(Colaborador $colaborador, CarbonInterface $data): Collection
    {
        $vinculos = ColaboradorBeneficio::query()
            ->where('colaborador_id', $colaborador->id)
            ->vigentesEm($data)
            ->with('beneficio')
            ->get();

        $tabela = BeneficioValor::query()
            ->whereIn('beneficio_id', $vinculos->pluck('beneficio_id'))
            ->vigentesEm($data)
            ->get()
            ->keyBy('beneficio_id');

        return $vinculos->map(function (ColaboradorBeneficio $vinculo) use ($tabela) {
            $personalizado = $vinculo->valor_personalizado !== null;
            $valor = $personalizado ? $vinculo->valor_personalizado : $tabela->get($vinculo->beneficio_id)?->valor;

            return [
                'vinculo' => $vinculo,
                'beneficio' => $vinculo->beneficio,
                'valor' => $valor === null ? null : Dinheiro::centavos($valor),
                'personalizado' => $personalizado,
            ];
        })->sortBy(fn ($item) => $item['beneficio']->nome)->values();
    }
}

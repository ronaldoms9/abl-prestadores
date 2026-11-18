<?php

namespace App\Services\Fechamento;

use App\Enums\TipoItemFechamento;
use Carbon\CarbonInterface;

/** O que a CalculadoraFechamento apurou de um colaborador em uma competência. Nada aqui foi gravado. */
final class ResultadoFechamento
{
    /**
     * @param  list<ItemCalculado>  $itens
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly CarbonInterface $inicio,
        public readonly CarbonInterface $fim,
        public readonly int $diasPeriodo,
        public readonly int $diasTrabalhados,
        public readonly array $itens,
        public readonly array $avisos = [],
    ) {
    }

    public function remuneracao(): int
    {
        return $this->somar([TipoItemFechamento::Remuneracao]);
    }

    public function beneficios(): int
    {
        return $this->somar([TipoItemFechamento::Beneficio]);
    }

    /** Créditos menos débitos. */
    public function ajustes(): int
    {
        return $this->somar([TipoItemFechamento::Ajuste, TipoItemFechamento::Desconto]);
    }

    public function liquido(): int
    {
        return $this->remuneracao() + $this->beneficios() + $this->ajustes();
    }

    /** @param  list<TipoItemFechamento>  $tipos */
    private function somar(array $tipos): int
    {
        return array_sum(array_map(fn (ItemCalculado $i) => in_array($i->tipo, $tipos, true) ? $i->total : 0, $this->itens));
    }
}

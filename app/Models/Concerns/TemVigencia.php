<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registro com vigência de início e fim. O fim é exclusivo: a linha encerrada
 * em 10/05 vale até 09/05 e a nova começa em 10/05, então em cada dia vale
 * uma só. Fim nulo é a linha aberta (vigente). O model muda as colunas em
 * colunasVigencia() quando elas não são inicio e fim.
 */
trait TemVigencia
{
    /** @return array{0: string, 1: string} */
    protected function colunasVigencia(): array
    {
        return ['inicio', 'fim'];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($this->qualificarColuna($this->colunasVigencia()[1]));
    }

    public function scopeVigentesEm(Builder $query, CarbonInterface|string $data): Builder
    {
        [$inicio, $fim] = array_map(fn ($c) => $this->qualificarColuna($c), $this->colunasVigencia());
        $dia = $data instanceof CarbonInterface ? $data->toDateString() : substr($data, 0, 10);

        return $query->whereDate($inicio, '<=', $dia)
            ->where(fn (Builder $q) => $q->whereNull($fim)->orWhereDate($fim, '>', $dia));
    }

    private function qualificarColuna(string $coluna): string
    {
        return $this->getTable().'.'.$coluna;
    }
}

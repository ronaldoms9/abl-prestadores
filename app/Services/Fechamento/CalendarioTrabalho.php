<?php

namespace App\Services\Fechamento;

use App\Enums\AbrangenciaFeriado;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\Feriado;
use App\Models\FeriasSolicitacao;
use App\Models\Ocorrencia;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Dias de trabalho de um colaborador em um período (datas inclusive): dias de
 * semana (e sábado, se configurado) menos feriados da obra, férias aprovadas e
 * faltas ou afastamentos não abonados. Só o benefício por dia usa isto.
 */
class CalendarioTrabalho
{
    public function sabadoTrabalhado(): bool
    {
        return (bool) Configuracao::obter('calculo.sabado_trabalhado', false);
    }

    public function diasTrabalhados(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim): int
    {
        return count($this->diasDeTrabalho($colaborador, $inicio, $fim));
    }

    /**
     * Cada dia trabalhado do período, por data (Y-m-d).
     *
     * @return array<string, true>
     */
    public function diasDeTrabalho(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->startOfDay();

        if ($fim->lt($inicio)) {
            return [];
        }

        $dias = [];
        $sabado = $this->sabadoTrabalhado();
        for ($dia = $inicio->copy(); $dia->lte($fim); $dia->addDay()) {
            if (! $dia->isSunday() && ($sabado || ! $dia->isSaturday())) {
                $dias[$dia->toDateString()] = true;
            }
        }

        foreach ($this->diasDeFolga($colaborador, $inicio, $fim) as $data) {
            unset($dias[$data]);
        }

        return $dias;
    }

    /**
     * Dias em que o colaborador não trabalha por feriado, férias ou falta não abonada.
     *
     * @return list<string>
     */
    private function diasDeFolga(Colaborador $colaborador, Carbon $inicio, Carbon $fim): array
    {
        $folgas = [];

        Feriado::query()
            ->whereDate('data', '>=', $inicio)->whereDate('data', '<=', $fim)
            ->where(fn ($q) => $q->where('abrangencia', AbrangenciaFeriado::Nacional->value)
                ->when($colaborador->obra_id, fn ($w) => $w->orWhere('obra_id', $colaborador->obra_id)))
            ->get()
            ->each(function (Feriado $f) use (&$folgas) {
                $folgas[] = $f->data->toDateString();
            });

        FeriasSolicitacao::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('status', StatusSolicitacaoFerias::Aprovada->value)
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->get()
            ->each(function (FeriasSolicitacao $f) use (&$folgas, $inicio, $fim) {
                array_push($folgas, ...$this->intervalo($f->inicio, $f->fim, $inicio, $fim));
            });

        Ocorrencia::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('abona', false)
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->get()
            ->each(function (Ocorrencia $o) use (&$folgas, $inicio, $fim) {
                array_push($folgas, ...$this->intervalo($o->inicio, $o->fim, $inicio, $fim));
            });

        return $folgas;
    }

    /** @return list<string> */
    private function intervalo(CarbonInterface $de, CarbonInterface $ate, Carbon $limiteInicio, Carbon $limiteFim): array
    {
        $dias = [];
        $dia = $de->copy()->startOfDay()->max($limiteInicio);
        $fim = $ate->copy()->startOfDay()->min($limiteFim);

        for (; $dia->lte($fim); $dia->addDay()) {
            $dias[] = $dia->toDateString();
        }

        return $dias;
    }
}

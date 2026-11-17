<?php

namespace App\Console\Commands;

use App\Actions\Ferias\GerarPeriodosAquisitivos;
use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use Illuminate\Console\Command;

/** Abre, todo dia, o período aquisitivo que começa hoje (e completa o que faltar) para cada colaborador ativo. */
class FeriasPeriodos extends Command
{
    protected $signature = 'ferias:periodos';

    protected $description = 'Gera os períodos aquisitivos de férias que já começaram';

    public function handle(GerarPeriodosAquisitivos $gerar): int
    {
        $criados = 0;
        $colaboradores = 0;

        Colaborador::query()->where('status', StatusColaborador::Ativo->value)->whereNotNull('data_admissao')->orderBy('id')->each(function (Colaborador $colaborador) use ($gerar, &$criados, &$colaboradores) {
            $novos = $gerar->executar($colaborador)->count();
            $criados += $novos;
            $colaboradores += $novos > 0 ? 1 : 0;
        });

        $this->info($criados === 0 ? 'Nenhum período novo.' : "{$criados} período(s) criado(s) para {$colaboradores} colaborador(es).");

        return self::SUCCESS;
    }
}

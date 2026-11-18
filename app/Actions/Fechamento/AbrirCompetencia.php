<?php

namespace App\Actions\Fechamento;

use App\Enums\StatusCompetencia;
use App\Jobs\CalcularCompetencia;
use App\Models\Competencia;
use App\Support\Auditoria;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Abre a competência de um mês (AAAA-MM) e dispara o cálculo em lote. */
class AbrirCompetencia
{
    public function executar(string $anoMes, ?Carbon $hoje = null): Competencia
    {
        $hoje ??= now();

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $anoMes)) {
            throw ValidationException::withMessages(['ano_mes' => 'Informe o mês no formato AAAA-MM.']);
        }

        if ($anoMes > $hoje->format('Y-m')) {
            throw ValidationException::withMessages(['ano_mes' => 'Não dá para abrir a competência de um mês que ainda não começou.']);
        }

        if (Competencia::query()->where('ano_mes', $anoMes)->exists()) {
            throw ValidationException::withMessages(['ano_mes' => 'A competência de '.substr($anoMes, 5, 2).'/'.substr($anoMes, 0, 4).' já foi aberta.']);
        }

        $competencia = Competencia::create(['ano_mes' => $anoMes, 'status' => StatusCompetencia::Aberta]);
        Auditoria::registrar('competencia-aberta', $competencia, ['ano_mes' => $anoMes]);

        CalcularCompetencia::dispatch($competencia->id);

        return $competencia->refresh();
    }
}

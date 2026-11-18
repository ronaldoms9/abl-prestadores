<?php

namespace App\Jobs;

use App\Actions\Fechamento\CalcularFechamentoColaborador;
use App\Enums\StatusCompetencia;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Support\Auditoria;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Calcula todos os colaboradores que estiveram ativos em algum dia do mês e
 * leva a competência para a conferência. Pode rodar de novo (recalcula tudo).
 */
class CalcularCompetencia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $competenciaId)
    {
    }

    public function handle(CalcularFechamentoColaborador $calcular): void
    {
        $competencia = Competencia::query()->find($this->competenciaId);

        if ($competencia === null || $competencia->status->imutavel()) {
            return;
        }

        $primeiro = Carbon::createFromFormat('Y-m-d', $competencia->ano_mes.'-01')->startOfDay();
        $ultimo = $primeiro->copy()->endOfMonth()->startOfDay();
        $calculados = 0;

        Colaborador::query()
            ->whereNotNull('data_admissao')
            ->whereDate('data_admissao', '<=', $ultimo)
            ->where(fn ($q) => $q->whereNull('data_desligamento')->orWhereDate('data_desligamento', '>=', $primeiro))
            ->orderBy('id')
            ->each(function (Colaborador $colaborador) use ($calcular, $competencia, &$calculados) {
                $calcular->executar($colaborador, $competencia) !== null && $calculados++;
            });

        // Quem deixou de ter período no mês (admissão ou desligamento corrigidos) perde o fechamento.
        $competencia->fechamentos()->whereDoesntHave('colaborador', fn ($q) => $q
            ->whereNotNull('data_admissao')
            ->whereDate('data_admissao', '<=', $ultimo)
            ->where(fn ($w) => $w->whereNull('data_desligamento')->orWhereDate('data_desligamento', '>=', $primeiro)))
            ->each(function ($f) {
                $f->itens()->delete();
                $f->delete();
            });

        $competencia->update(['status' => StatusCompetencia::EmConferencia]);
        Auditoria::registrar('competencia-calculada', $competencia, ['colaboradores' => $calculados]);
    }
}

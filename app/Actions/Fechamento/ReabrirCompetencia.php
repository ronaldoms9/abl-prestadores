<?php

namespace App\Actions\Fechamento;

use App\Enums\StatusCompetencia;
use App\Enums\StatusFechamento;
use App\Models\Competencia;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Volta uma competência aprovada para a conferência. Só se nada foi enviado adiante; exige justificativa. */
class ReabrirCompetencia
{
    public function executar(Competencia $competencia, string $justificativa): Competencia
    {
        $justificativa = trim($justificativa);
        if ($justificativa === '') {
            throw ValidationException::withMessages(['justificativa' => 'Explique por que a competência está sendo reaberta.']);
        }

        return DB::transaction(function () use ($competencia, $justificativa) {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($competencia->id);

            if ($competencia->status !== StatusCompetencia::Aprovada) {
                throw ValidationException::withMessages(['competencia' => 'Só uma competência aprovada pode ser reaberta.']);
            }

            $adiante = $competencia->fechamentos()->where('status', '!=', StatusFechamento::AguardandoNota->value)->count();
            if ($adiante > 0) {
                throw ValidationException::withMessages(['competencia' => "{$adiante} fechamento(s) já seguiram para a etapa de notas ou títulos; a competência não pode mais ser reaberta."]);
            }

            $total = (float) $competencia->fechamentos()->sum('total_liquido');

            foreach ($competencia->fechamentos()->get() as $f) {
                $f->update([
                    'status' => StatusFechamento::Calculado,
                    'snapshot' => ['avisos' => $f->snapshot['avisos'] ?? [], 'periodo' => $f->snapshot['periodo'] ?? null],
                ]);
            }

            $competencia->update(['status' => StatusCompetencia::EmConferencia, 'aprovada_por' => null, 'aprovada_em' => null]);
            Auditoria::registrar('competencia-reaberta', $competencia, ['justificativa' => $justificativa, 'total_aprovado' => number_format($total, 2, '.', '')]);

            return $competencia;
        });
    }
}

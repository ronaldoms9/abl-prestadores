<?php

namespace App\Actions\Notas;

use App\Enums\StatusNota;
use App\Events\NotaAprovada;
use App\Models\NotaFiscal;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Aprovação pelo financeiro. Nota divergente só com justificativa. */
class AprovarNota
{
    public function executar(NotaFiscal $nota, User $aprovador, ?string $justificativa = null): NotaFiscal
    {
        $justificativa = $justificativa === null ? null : trim($justificativa);

        $nota = DB::transaction(function () use ($nota, $aprovador, $justificativa) {
            $nota = NotaFiscal::query()->lockForUpdate()->findOrFail($nota->id);

            if (! $nota->status->aguardaDecisao()) {
                throw ValidationException::withMessages(['nota' => 'Esta nota já foi decidida ou ainda não foi enviada.']);
            }

            if ($nota->status === StatusNota::Divergente && ($justificativa === null || $justificativa === '')) {
                throw ValidationException::withMessages(['justificativa' => 'A nota tem divergências: explique por que está sendo aprovada.']);
            }

            $nota->update([
                'status' => StatusNota::Aprovada,
                'justificativa_aprovacao' => $nota->status === StatusNota::Divergente ? $justificativa : null,
                'decidido_por' => $aprovador->id,
                'decidido_em' => now(),
            ]);
            Auditoria::registrar('nota-aprovada', $nota, ['com_divergencia' => $nota->divergencias !== null, 'justificativa' => $justificativa]);

            return $nota;
        });

        NotaAprovada::dispatch($nota);

        return $nota;
    }
}

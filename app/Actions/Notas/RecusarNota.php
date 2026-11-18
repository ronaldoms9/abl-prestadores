<?php

namespace App\Actions\Notas;

use App\Enums\StatusNota;
use App\Models\NotaFiscal;
use App\Models\User;
use App\Notifications\NotaRecusada;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Recusa com motivo obrigatório; o colaborador é avisado e pode reenviar. */
class RecusarNota
{
    public function executar(NotaFiscal $nota, User $decisor, string $motivo): NotaFiscal
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo da recusa.']);
        }

        $nota = DB::transaction(function () use ($nota, $decisor, $motivo) {
            $nota = NotaFiscal::query()->with(['colaborador.pessoaFisica', 'competencia'])->lockForUpdate()->findOrFail($nota->id);

            if (! $nota->status->aguardaDecisao()) {
                throw ValidationException::withMessages(['nota' => 'Esta nota já foi decidida ou ainda não foi enviada.']);
            }

            $nota->update(['status' => StatusNota::Recusada, 'motivo_recusa' => $motivo, 'decidido_por' => $decisor->id, 'decidido_em' => now()]);
            Auditoria::registrar('nota-recusada', $nota, ['motivo' => $motivo]);

            return $nota;
        });

        User::query()->ativos()->where('pessoa_fisica_id', $nota->colaborador->pessoa_fisica_id)->get()
            ->each(fn (User $u) => $u->notify(new NotaRecusada($nota)));

        return $nota;
    }
}

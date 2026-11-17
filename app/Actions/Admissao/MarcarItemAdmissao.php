<?php

namespace App\Actions\Admissao;

use App\Enums\StatusColaborador;
use App\Models\AdmissaoChecklist;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Validation\ValidationException;

/** Marca ou desmarca um item manual, com autor e data. Item automático é do sistema; admissão concluída não muda mais. */
class MarcarItemAdmissao
{
    public function executar(AdmissaoChecklist $linha, bool $concluido, User $por, ?string $observacao = null): AdmissaoChecklist
    {
        $linha->loadMissing('colaborador');

        if ($linha->chave !== null) {
            throw ValidationException::withMessages(['item' => 'Este item é marcado pelo sistema, a partir do cadastro.']);
        }

        if ($linha->colaborador->status !== StatusColaborador::PreAdmissao) {
            throw ValidationException::withMessages(['item' => 'A admissão já foi concluída.']);
        }

        $linha->update([
            'concluido' => $concluido,
            'concluido_por' => $concluido ? $por->id : null,
            'concluido_em' => $concluido ? now() : null,
            'observacao' => $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null,
        ]);

        Auditoria::registrar('admissao-item-concluido', $linha->colaborador, ['item' => $linha->item, 'concluido' => $concluido], $por);

        return $linha;
    }
}

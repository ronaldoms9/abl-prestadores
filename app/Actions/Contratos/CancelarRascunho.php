<?php

namespace App\Actions\Contratos;

use App\Enums\StatusContrato;
use App\Models\Contrato;
use Illuminate\Validation\ValidationException;

/** Cancela um rascunho (exclusão lógica). Contrato gerado não se cancela: corrige-se com aditivo. */
class CancelarRascunho
{
    public function executar(Contrato $contrato): void
    {
        if ($contrato->status !== StatusContrato::Rascunho) {
            throw ValidationException::withMessages(['contrato' => 'Só rascunho pode ser cancelado. Contrato gerado é corrigido com um aditivo.']);
        }

        $contrato->delete();
    }
}

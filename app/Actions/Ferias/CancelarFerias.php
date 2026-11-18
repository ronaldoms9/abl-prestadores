<?php

namespace App\Actions\Ferias;

use App\Enums\StatusSolicitacaoFerias;
use App\Models\FeriasSolicitacao;
use App\Support\Auditoria;
use App\Support\Competencias\PrimeiroMesFechado;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cancela férias pendentes (sem efeito no saldo) ou aprovadas (devolvendo os dias, se o mês não estiver fechado). */
class CancelarFerias
{
    public function __construct(private readonly AjustarSaldoFerias $saldo, private readonly PrimeiroMesFechado $meses)
    {
    }

    public function executar(FeriasSolicitacao $solicitacao): FeriasSolicitacao
    {
        return DB::transaction(function () use ($solicitacao) {
            $solicitacao = FeriasSolicitacao::query()->with(['colaborador', 'periodo'])->lockForUpdate()->findOrFail($solicitacao->id);

            if (! in_array($solicitacao->status, [StatusSolicitacaoFerias::Pendente, StatusSolicitacaoFerias::Aprovada], true)) {
                throw ValidationException::withMessages(['solicitacao' => 'Só dá para cancelar férias pendentes ou aprovadas.']);
            }

            if ($solicitacao->status === StatusSolicitacaoFerias::Aprovada) {
                if ($fechado = $this->meses->em($solicitacao->inicio, $solicitacao->fim)) {
                    throw ValidationException::withMessages(['solicitacao' => 'A competência de '.$fechado->format('m/Y').' já está fechada. As férias aprovadas nesse período não podem ser canceladas.']);
                }

                $this->saldo->devolver($solicitacao->periodo, $solicitacao->dias);
            }

            $solicitacao->update(['status' => StatusSolicitacaoFerias::Cancelada]);
            Auditoria::registrar('ferias-cancelada', $solicitacao->colaborador, ['solicitacao' => $solicitacao->id, 'dias' => $solicitacao->dias]);

            return $solicitacao;
        });
    }
}

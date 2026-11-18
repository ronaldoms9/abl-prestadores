<?php

namespace App\Actions\Ferias;

use App\Enums\DecisaoFerias;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\FeriasAprovacao;
use App\Models\FeriasSolicitacao;
use App\Models\User;
use App\Notifications\FeriasDecidida;
use App\Notifications\FeriasSolicitada;
use App\Support\Auditoria;
use App\Support\Competencias\PrimeiroMesFechado;
use App\Support\Ferias\AprovadoresFerias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Aprovação ou recusa de uma solicitação por quem está na fila do nível atual.
 * A fila é calculada na hora da decisão. A aprovação do último nível baixa o saldo.
 */
class DecidirFerias
{
    public function __construct(
        private readonly SincronizarFilaFerias $fila,
        private readonly AprovadoresFerias $aprovadores,
        private readonly AjustarSaldoFerias $saldo,
        private readonly PrimeiroMesFechado $meses,
    ) {
    }

    public function executar(FeriasSolicitacao $solicitacao, User $aprovador, DecisaoFerias $decisao, ?string $observacao = null): FeriasSolicitacao
    {
        if ($decisao === DecisaoFerias::Pulada) {
            throw ValidationException::withMessages(['decisao' => 'Decisão inválida.']);
        }

        $observacao = $observacao === null ? null : trim($observacao);
        if ($decisao === DecisaoFerias::Recusada && ($observacao === null || $observacao === '')) {
            throw ValidationException::withMessages(['observacao' => 'Explique o motivo da recusa.']);
        }

        $proximos = collect();

        $resultado = DB::transaction(function () use ($solicitacao, $aprovador, $decisao, $observacao, &$proximos) {
            $solicitacao = FeriasSolicitacao::query()->with(['colaborador.pessoaFisica', 'periodo', 'solicitante'])->lockForUpdate()->findOrFail($solicitacao->id);

            if ($solicitacao->status !== StatusSolicitacaoFerias::Pendente) {
                throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi decidida ou cancelada.']);
            }

            $fila = $this->fila->executar($solicitacao);

            if (! $fila['usuarios']->contains('id', $aprovador->id)) {
                throw ValidationException::withMessages(['solicitacao' => 'Você não está entre os aprovadores desta solicitação neste nível.']);
            }

            FeriasAprovacao::create([
                'ferias_solicitacao_id' => $solicitacao->id,
                'nivel' => $fila['nivel'],
                'aprovador_id' => $aprovador->id,
                'decisao' => $decisao,
                'observacao' => $observacao,
                'decidido_em' => now(),
                'escalada' => $fila['fallback'],
                'motivo_escalada' => $fila['fallback'] ? 'Decidida por quem tem '.AprovadoresFerias::PERMISSAO_QUALQUER.'.' : null,
            ]);

            if ($decisao === DecisaoFerias::Recusada) {
                $solicitacao->update(['status' => StatusSolicitacaoFerias::Recusada]);
                Auditoria::registrar('ferias-recusada', $solicitacao->colaborador, ['solicitacao' => $solicitacao->id, 'nivel' => $fila['nivel']]);

                return $solicitacao;
            }

            if ($fila['ultimo']) {
                if ($fechado = $this->meses->em($solicitacao->inicio, $solicitacao->fim)) {
                    throw ValidationException::withMessages(['solicitacao' => 'A competência de '.$fechado->format('m/Y').' já está fechada. As férias não podem ser aprovadas nesse período.']);
                }

                $this->saldo->baixar($solicitacao->periodo, $solicitacao->dias);
                $solicitacao->update(['status' => StatusSolicitacaoFerias::Aprovada]);
                Auditoria::registrar('ferias-aprovada', $solicitacao->colaborador, ['solicitacao' => $solicitacao->id, 'dias' => $solicitacao->dias]);

                return $solicitacao;
            }

            // Há outro nível: segue para ele (e pula, registrando, os que estiverem vazios).
            $solicitacao->update(['nivel_atual' => $fila['nivel'] + 1]);
            $proximos = $this->fila->executar($solicitacao->refresh())['usuarios'];
            Auditoria::registrar('ferias-nivel-aprovado', $solicitacao->colaborador, ['solicitacao' => $solicitacao->id, 'nivel' => $fila['nivel']]);

            return $solicitacao;
        });

        $this->avisar($resultado, $proximos, $observacao);

        return $resultado;
    }

    /** @param  \Illuminate\Support\Collection<int, User>  $proximos */
    private function avisar(FeriasSolicitacao $solicitacao, $proximos, ?string $observacao): void
    {
        if ($solicitacao->status !== StatusSolicitacaoFerias::Pendente && $solicitacao->solicitante !== null) {
            $solicitacao->solicitante->notify(new FeriasDecidida($solicitacao, $observacao));
        } elseif ($proximos->isNotEmpty()) {
            Notification::send($proximos, new FeriasSolicitada($solicitacao));
        }
    }
}

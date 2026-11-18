<?php

namespace App\Actions\Ferias;

use App\Enums\DecisaoFerias;
use App\Models\FeriasAprovacao;
use App\Models\FeriasSolicitacao;
use App\Support\AvisosAdmin;
use App\Support\Ferias\AprovadoresFerias;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Leva a solicitação ao nível que de fato espera decisão: grava como escalada
 * cada nível pulado por falta de aprovador e atualiza `nivel_atual`. Idempotente.
 */
class SincronizarFilaFerias
{
    public function __construct(private readonly AprovadoresFerias $aprovadores)
    {
    }

    /** @return array{nivel: int, usuarios: \Illuminate\Support\Collection<int, \App\Models\User>, puladas: list<array{nivel: int, motivo: string}>, ultimo: bool, fallback: bool} */
    public function executar(FeriasSolicitacao $solicitacao, ?CarbonInterface $hoje = null): array
    {
        $fila = $this->aprovadores->resolver($solicitacao, $hoje ?? now());

        foreach ($fila['puladas'] as $pulada) {
            $this->registrarEscalada($solicitacao, $pulada['nivel'], $pulada['motivo']);
        }

        if ($fila['fallback']) {
            $this->registrarEscalada($solicitacao, $fila['nivel'], 'Nenhum nível tem aprovador elegível; encaminhada a quem tem a permissão '.AprovadoresFerias::PERMISSAO_QUALQUER.'.');

            if ($fila['usuarios']->isEmpty()) {
                Log::warning("Solicitação de férias #{$solicitacao->id} sem nenhum aprovador elegível.");
                AvisosAdmin::registrarAlertaSemDestinatario(AprovadoresFerias::PERMISSAO_QUALQUER, 'Aprovação de férias sem aprovador elegível');
            } else {
                AvisosAdmin::limparAlertaSemDestinatario(AprovadoresFerias::PERMISSAO_QUALQUER);
            }
        }

        if ($solicitacao->nivel_atual !== $fila['nivel']) {
            $solicitacao->update(['nivel_atual' => $fila['nivel']]);
        }

        return $fila;
    }

    private function registrarEscalada(FeriasSolicitacao $solicitacao, int $nivel, string $motivo): void
    {
        FeriasAprovacao::query()->firstOrCreate(
            ['ferias_solicitacao_id' => $solicitacao->id, 'nivel' => $nivel, 'decisao' => DecisaoFerias::Pulada->value],
            ['escalada' => true, 'motivo_escalada' => $motivo, 'decidido_em' => now()],
        );
    }
}

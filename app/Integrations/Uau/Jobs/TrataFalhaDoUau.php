<?php

namespace App\Integrations\Uau\Jobs;

use App\Actions\Uau\ImportacaoSuspensa;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use Closure;

/**
 * Política de erros da integração com o UAU (D5.8 da fatia 5).
 *
 * - UauIndisponivel: relançada; o worker da fila tenta de novo conforme $tries
 *   e $backoff do job (sem release() aqui, para a espera ter uma fonte só).
 * - UauRejeitou e ImportacaoSuspensa: chama fail() para não retentar.
 */
trait TrataFalhaDoUau
{
    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T|null
     */
    protected function executar(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (UauRejeitou|ImportacaoSuspensa $e) {
            $this->fail($e);

            return null;
        }
    }
}

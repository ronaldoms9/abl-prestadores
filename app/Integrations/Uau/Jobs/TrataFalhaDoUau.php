<?php

namespace App\Integrations\Uau\Jobs;

use App\Actions\Uau\ImportacaoSuspensa;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use Closure;

/**
 * Política de erros da integração com o UAU (D5.8 da fatia 5).
 *
 * - UauIndisponivel: relançada para a fila tentar de novo conforme tries e backoff.
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
        } catch (UauIndisponivel $e) {
            if ($this->attempts() < $this->tries) {
                $delay = $this->backoff[$this->attempts() - 1] ?? 60;
                $this->release($delay);
            }

            throw $e;
        } catch (UauRejeitou|ImportacaoSuspensa $e) {
            $this->fail($e);

            return null;
        }
    }
}

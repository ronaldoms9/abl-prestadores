<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida a assinatura que o WAHA põe no corpo bruto do webhook.
 *
 * Sem isso o endpoint é público: qualquer um posta uma "mensagem de cliente" e
 * faz o bot responder — e pagar tokens.
 */
class VerifyWahaHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $chave = (string) config('whatsapp.hmac_key');

        if ($chave === '') {
            Log::error('Webhook do WAHA recusado: WAHA_HMAC_KEY não configurada.');
            abort(401);
        }

        $algoritmo = strtolower((string) $request->header('X-Webhook-Hmac-Algorithm'));

        if ($algoritmo !== config('whatsapp.webhook.algoritmo')) {
            Log::warning('Webhook do WAHA recusado: algoritmo inesperado.', ['algoritmo' => $algoritmo]);
            abort(401);
        }

        $esperado = hash_hmac('sha512', $request->getContent(), $chave);

        // hash_equals, nunca ===: comparação de tempo constante.
        if (! hash_equals($esperado, (string) $request->header('X-Webhook-Hmac'))) {
            Log::warning('Webhook do WAHA recusado: assinatura inválida.', [
                'request_id' => $request->header('X-Webhook-Request-Id'),
            ]);
            abort(401);
        }

        if (! $this->dentroDaJanela($request)) {
            Log::warning('Webhook do WAHA recusado: fora da janela de tempo.', [
                'timestamp' => $request->header('X-Webhook-Timestamp'),
            ]);
            abort(401);
        }

        return $next($request);
    }

    /** Barra reenvio de uma requisição legítima capturada antes. */
    private function dentroDaJanela(Request $request): bool
    {
        $carimbo = $request->header('X-Webhook-Timestamp');

        if (! is_numeric($carimbo)) {
            return false;
        }

        $segundos = (int) $carimbo / 1000;
        $janela = (int) config('whatsapp.webhook.janela_segundos');

        return abs(now()->getTimestamp() - $segundos) <= $janela;
    }
}

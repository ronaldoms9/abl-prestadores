<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\Tenant;
use App\WhatsApp\Waha\WahaWebhookTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fino de propósito: traduz, descarta, resolve o tenant, despacha e devolve 200.
 *
 * Nenhuma chamada a LLM no ciclo HTTP: o WAHA tem política de retentativa e um
 * webhook lento vira mensagem duplicada para o cliente final.
 */
class WahaWebhookController extends Controller
{
    public function __construct(private readonly WahaWebhookTranslator $tradutor)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if ($this->repetido($request, $payload)) {
            return response()->json(['status' => 'repetido']);
        }

        // Ciclo de vida da sessão: não vira conversa, mas é o que diz se o
        // número do cliente caiu e precisa de QR novo.
        if ($snapshot = $this->tradutor->translateStatus($payload)) {
            Log::info('Sessão do WAHA mudou de estado.', [
                'session' => $snapshot->session,
                'estado' => $snapshot->state->value,
            ]);

            Tenant::where('waha_session', $snapshot->session)->first()?->touch();

            return response()->json(['status' => 'sessao', 'estado' => $snapshot->state->value]);
        }

        $mensagem = $this->tradutor->translate($payload);

        if ($mensagem === null) {
            return response()->json(['status' => 'ignorado']);
        }

        $tenant = Tenant::where('waha_session', $mensagem->session)->first();

        if ($tenant === null) {
            Log::warning('Webhook do WAHA para sessão sem tenant.', ['session' => $mensagem->session]);

            return response()->json(['status' => 'sem-tenant']);
        }

        ProcessIncomingMessage::dispatch($tenant->id, $mensagem);

        return response()->json(['status' => 'enfileirado']);
    }

    /**
     * A retentativa do WAHA é de 3 tentativas: sem isso, uma resposta lenta
     * vira três respostas ao cliente final.
     */
    private function repetido(Request $request, array $payload): bool
    {
        $id = $request->header('X-Webhook-Request-Id')
            ?? $payload['payload']['id']
            ?? $payload['id']
            ?? null;

        if (! is_string($id) || $id === '') {
            return false;
        }

        $minutos = (int) config('whatsapp.webhook.idempotencia_minutos');

        return ! Cache::add('waha:webhook:'.sha1($id), true, now()->addMinutes($minutos));
    }
}

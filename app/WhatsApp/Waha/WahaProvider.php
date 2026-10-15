<?php

namespace App\WhatsApp\Waha;

use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Data\SessionSnapshot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WahaProvider implements WhatsAppProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 5,
        private readonly ?string $webhookUrl = null,
        private readonly ?string $hmacKey = null,
    ) {
    }

    public function startSession(string $session): void
    {
        $resposta = $this->http()->post('/api/sessions', [
            'name' => $session,
            'start' => true,
            'config' => $this->configDaSessao(),
        ]);

        // 422 = sessão já existe; nesse caso basta (re)iniciar a que está lá.
        if ($resposta->status() === 422 || $resposta->status() === 409) {
            $this->garantir($this->http()->post("/api/sessions/{$session}/start"), "start {$session}");

            return;
        }

        $this->garantir($resposta, "criar sessão {$session}");
    }

    public function stopSession(string $session): void
    {
        $this->garantir($this->http()->post("/api/sessions/{$session}/stop"), "stop {$session}");
    }

    public function getQr(string $session): string
    {
        // format=image devolve o PNG; format=raw devolveria a string de
        // pareamento ("https://wa.me/settings/linked_devices#2@..."), que não
        // é o que o contrato promete.
        $resposta = $this->garantir(
            $this->http()->get("/api/{$session}/auth/qr", ['format' => 'image']),
            "qr {$session}"
        );

        if (str_contains((string) $resposta->header('Content-Type'), 'json')) {
            $dados = $resposta->json();

            if (isset($dados['data']) && is_string($dados['data'])) {
                return $dados['data'];
            }

            throw new WahaException("QR da sessão {$session} não veio como imagem.", $resposta->status(), $dados ?? []);
        }

        return base64_encode($resposta->body());
    }

    public function getStatus(string $session): SessionSnapshot
    {
        $resposta = $this->garantir($this->http()->get("/api/sessions/{$session}"), "status {$session}");

        return SessionSnapshot::doPayload($resposta->json() ?? [], $session);
    }

    public function sendSeen(string $session, string $chatId): void
    {
        $this->garantir(
            $this->http()->post('/api/sendSeen', ['session' => $session, 'chatId' => $chatId]),
            'sendSeen'
        );
    }

    public function startTyping(string $session, string $chatId): void
    {
        $this->garantir(
            $this->http()->post('/api/startTyping', ['session' => $session, 'chatId' => $chatId]),
            'startTyping'
        );
    }

    public function stopTyping(string $session, string $chatId): void
    {
        $this->garantir(
            $this->http()->post('/api/stopTyping', ['session' => $session, 'chatId' => $chatId]),
            'stopTyping'
        );
    }

    public function sendText(string $session, string $chatId, string $text): void
    {
        $this->garantir(
            $this->http()->post('/api/sendText', [
                'session' => $session,
                'chatId' => $chatId,
                'text' => $text,
            ]),
            'sendText'
        );
    }

    public function downloadMedia(string $url): string
    {
        // O arquivo local do WAHA expira em 180s: isso aqui é chamado na hora
        // em que a mensagem chega, nunca depois do debounce.
        $resposta = $this->garantir(
            Http::withHeaders($this->cabecalhos())->timeout(30)->get($url),
            'downloadMedia'
        );

        $extensao = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'bin';
        $caminho = 'whatsapp-media/'.Str::uuid()->toString().'.'.$extensao;

        Storage::disk('local')->put($caminho, $resposta->body());

        return Storage::disk('local')->path($caminho);
    }

    /** Configuração de webhook aplicada a toda sessão criada por aqui. */
    private function configDaSessao(): array
    {
        $webhook = array_filter([
            'url' => $this->webhookUrl,
            'events' => ['message', 'session.status'],
            'hmac' => $this->hmacKey ? ['key' => $this->hmacKey] : null,
            'retries' => ['policy' => 'linear', 'attempts' => 3, 'delaySeconds' => 5],
        ]);

        return $this->webhookUrl ? ['webhooks' => [$webhook]] : [];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders($this->cabecalhos())
            ->timeout($this->timeout);
    }

    private function cabecalhos(): array
    {
        return array_filter(['X-Api-Key' => $this->apiKey]);
    }

    private function garantir(Response $resposta, string $rota): Response
    {
        if ($resposta->failed()) {
            throw WahaException::daResposta($resposta, $rota);
        }

        return $resposta;
    }
}

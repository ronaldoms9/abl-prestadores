<?php

namespace App\OpenRouter;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly bool $zdr = true,
        private readonly int $timeout = 20,
        private readonly int $tentativas = 2,
    ) {
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, string>  $models  lista ordenada de fallback: principal primeiro
     */
    public function chat(array $messages, array $models, int $timeoutSeconds = 20): ChatResult
    {
        $corpo = [
            'model' => $models[0],
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'usage' => ['include' => true],
        ];

        // Reserva de outro provedor: a OpenRouter cai para ela sozinha.
        if (count($models) > 1) {
            $corpo['models'] = array_values($models);
        }

        if ($this->zdr) {
            $corpo['provider'] = ['zdr' => true];
        }

        try {
            $resposta = Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->apiKey)
                ->timeout($timeoutSeconds)
                ->retry($this->tentativas, 300, throw: false)
                ->post('/chat/completions', $corpo);
        } catch (ConnectionException $e) {
            throw new OpenRouterException('Falha de conexão com a OpenRouter: '.$e->getMessage(), previous: $e);
        }

        if ($resposta->failed()) {
            Log::warning('OpenRouter respondeu erro.', ['status' => $resposta->status(), 'corpo' => $resposta->body()]);

            throw new OpenRouterException(sprintf('OpenRouter respondeu %d.', $resposta->status()));
        }

        return ChatResult::daResposta($resposta->json() ?? []);
    }

    public function transcribe(string $caminhoArquivo): string
    {
        if (! is_file($caminhoArquivo)) {
            throw new OpenRouterException("Arquivo de áudio inexistente: {$caminhoArquivo}");
        }

        try {
            $resposta = Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->apiKey)
                ->timeout($this->timeout)
                ->attach('file', file_get_contents($caminhoArquivo), basename($caminhoArquivo))
                ->post('/audio/transcriptions', [
                    'model' => config('openrouter.modelo_transcricao'),
                ]);
        } catch (ConnectionException $e) {
            throw new OpenRouterException('Falha de conexão na transcrição: '.$e->getMessage(), previous: $e);
        }

        if ($resposta->failed()) {
            throw new OpenRouterException(sprintf('Transcrição respondeu %d: %s', $resposta->status(), $resposta->body()));
        }

        $texto = $resposta->json('text');

        if (! is_string($texto) || trim($texto) === '') {
            throw new OpenRouterException('Transcrição vazia.');
        }

        return trim($texto);
    }
}

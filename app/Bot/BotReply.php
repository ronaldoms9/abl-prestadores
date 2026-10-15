<?php

namespace App\Bot;

use JsonException;

final readonly class BotReply
{
    public function __construct(
        public string $resposta,
        public float $confianca,
        public bool $precisaHumano,
        public ?string $motivo = null,
    ) {
    }

    /** JSON inválido conta como falha do modelo, não como resposta. */
    public static function deJson(string $json): self
    {
        $limpo = trim($json);

        // Alguns modelos teimam em embrulhar o JSON em cerca de código.
        if (str_starts_with($limpo, '```')) {
            $limpo = trim(preg_replace('/^```(?:json)?|```$/m', '', $limpo));
        }

        try {
            $dados = json_decode($limpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BotReplyInvalida('Resposta do modelo não é JSON válido: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($dados) || ! isset($dados['resposta']) || ! is_string($dados['resposta'])) {
            throw new BotReplyInvalida('Resposta do modelo sem o campo "resposta".');
        }

        return new self(
            resposta: trim($dados['resposta']),
            confianca: isset($dados['confianca']) && is_numeric($dados['confianca']) ? (float) $dados['confianca'] : 0.0,
            precisaHumano: (bool) ($dados['precisa_humano'] ?? false),
            motivo: isset($dados['motivo']) && is_string($dados['motivo']) ? $dados['motivo'] : null,
        );
    }

    public function confiavel(float $limiar): bool
    {
        return ! $this->precisaHumano && $this->confianca >= $limiar && $this->resposta !== '';
    }
}

<?php

namespace App\OpenRouter;

final readonly class ChatResult
{
    public function __construct(
        public string $conteudo,
        public string $modelo,
        public int $tokensEntrada,
        public int $tokensSaida,
        public float $custoUsd,
    ) {
    }

    public static function daResposta(array $corpo): self
    {
        $conteudo = $corpo['choices'][0]['message']['content'] ?? null;

        if (! is_string($conteudo) || trim($conteudo) === '') {
            throw new OpenRouterException('Resposta da OpenRouter sem conteúdo utilizável.');
        }

        $uso = $corpo['usage'] ?? [];

        return new self(
            conteudo: $conteudo,
            modelo: (string) ($corpo['model'] ?? 'desconhecido'),
            tokensEntrada: (int) ($uso['prompt_tokens'] ?? 0),
            tokensSaida: (int) ($uso['completion_tokens'] ?? 0),
            custoUsd: (float) ($uso['cost'] ?? 0),
        );
    }
}

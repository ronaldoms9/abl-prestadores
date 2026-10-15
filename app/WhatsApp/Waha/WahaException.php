<?php

namespace App\WhatsApp\Waha;

use Illuminate\Http\Client\Response;
use RuntimeException;

class WahaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $corpo = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function daResposta(Response $resposta, string $rota): static
    {
        $corpo = $resposta->json() ?? [];
        $status = $resposta->status();

        $mensagem = $corpo['message'] ?? $corpo['error'] ?? $resposta->body();

        return match ($status) {
            463 => new WahaTimelockException($rota.': '.$mensagem, $status, $corpo),
            475 => new WahaMessageCappingException($rota.': '.$mensagem, $status, $corpo),
            default => new static(sprintf('WAHA %s respondeu %d: %s', $rota, $status, $mensagem), $status, $corpo),
        };
    }
}

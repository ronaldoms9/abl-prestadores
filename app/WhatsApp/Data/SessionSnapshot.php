<?php

namespace App\WhatsApp\Data;

use App\WhatsApp\Enums\SessionState;

final readonly class SessionSnapshot
{
    public function __construct(
        public string $session,
        public SessionState $state,
        public ?string $engine = null,
        public ?string $telefone = null,
    ) {
    }

    public static function doPayload(array $payload, ?string $session = null): self
    {
        return new self(
            session: $payload['name'] ?? $session ?? '',
            state: SessionState::deWaha($payload['status'] ?? null),
            engine: $payload['engine']['engine'] ?? ($payload['engine'] ?? null),
            telefone: $payload['me']['id'] ?? null,
        );
    }
}

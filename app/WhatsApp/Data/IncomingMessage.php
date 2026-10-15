<?php

namespace App\WhatsApp\Data;

use App\WhatsApp\Enums\MessageKind;
use Carbon\CarbonImmutable;

final readonly class IncomingMessage
{
    public function __construct(
        public string $session,
        public string $chatId,
        public string $messageId,
        public MessageKind $kind,
        public ?string $texto,
        public ?MediaRef $media,
        public bool $fromMe,
        public CarbonImmutable $timestamp,
    ) {
    }

    public function temTexto(): bool
    {
        return $this->texto !== null && trim($this->texto) !== '';
    }

    /** Só o número, sem o sufixo do WhatsApp: '5511999999999@c.us' -> '5511999999999'. */
    public function telefone(): string
    {
        return explode('@', $this->chatId)[0];
    }
}

<?php

namespace App\WhatsApp\Contracts;

use App\WhatsApp\Data\SessionSnapshot;

/**
 * Fronteira única com o WhatsApp.
 *
 * Sustenta três coisas ao mesmo tempo: testar sem celular, trocar o WAHA pela
 * API Oficial um dia, e impedir que o formato do WAHA vaze para o resto do
 * sistema. Nada além do que o produto usa hoje entra aqui.
 */
interface WhatsAppProvider
{
    public function startSession(string $session): void;

    public function stopSession(string $session): void;

    /** PNG em base64. */
    public function getQr(string $session): string;

    public function getStatus(string $session): SessionSnapshot;

    public function sendSeen(string $session, string $chatId): void;

    public function startTyping(string $session, string $chatId): void;

    public function stopTyping(string $session, string $chatId): void;

    public function sendText(string $session, string $chatId, string $text): void;

    /** Baixa a mídia e devolve o caminho local do arquivo. */
    public function downloadMedia(string $url): string;
}

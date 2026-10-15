<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\Tenant;
use App\WhatsApp\Contracts\WhatsAppProvider;

/**
 * Sequência recomendada pela documentação anti-banimento do WAHA:
 * sendSeen -> startTyping -> espera de 2 a 4s -> stopTyping -> sendText.
 *
 * A espera não é cosmética: resposta instantânea em WhatsApp grita "robô", e o
 * dono não quer que o cliente dele perceba.
 */
class MessageSender
{
    public function __construct(private readonly WhatsAppProvider $provider)
    {
    }

    public function enviar(Tenant $tenant, Conversation $conversa, string $texto): void
    {
        $sessao = (string) $tenant->waha_session;
        $chatId = $conversa->chat_id;

        $this->provider->sendSeen($sessao, $chatId);
        $this->provider->startTyping($sessao, $chatId);

        $this->esperar();

        $this->provider->stopTyping($sessao, $chatId);
        $this->provider->sendText($sessao, $chatId, $texto);
    }

    private function esperar(): void
    {
        $min = (int) config('whatsapp.digitando.min_segundos');
        $max = (int) config('whatsapp.digitando.max_segundos');

        if ($max <= 0) {
            return;
        }

        sleep(random_int(max($min, 0), $max));
    }
}

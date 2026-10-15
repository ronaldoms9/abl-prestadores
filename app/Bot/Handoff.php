<?php

namespace App\Bot;

use App\Models\Conversation;
use App\Models\MetricDaily;
use App\Models\Tenant;
use App\Notifications\ConversaPrecisaDeHumano;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escalar = marcar a conversa como humana, silenciar o bot naquele chat e
 * avisar o dono.
 *
 * Não existe caixa de entrada: ele responde pelo WhatsApp do celular dele, onde
 * as mensagens já estão.
 */
class Handoff
{
    public function __construct(private readonly MessageSender $sender)
    {
    }

    public function escalar(
        Tenant $tenant,
        Conversation $conversa,
        string $motivo,
        ?string $mensagemAoCliente = null,
        ?string $ultimaMensagemDoCliente = null,
    ): void {
        $conversa->passarParaHumano((int) config('whatsapp.handoff.silencio_horas'));

        if ($mensagemAoCliente !== null) {
            // Silêncio é a única falha que o dono nunca perdoa: ele perde venda
            // sem saber. Mesmo escalando, o cliente recebe alguma resposta.
            $this->sender->enviar($tenant, $conversa, $mensagemAoCliente);
        }

        MetricDaily::registrar($tenant->id, ['escaladas' => 1]);

        $donos = $tenant->users()->get();

        if ($donos->isNotEmpty()) {
            Notification::send($donos, new ConversaPrecisaDeHumano($conversa, $motivo, $ultimaMensagemDoCliente));
        }

        Log::info('Conversa escalada para humano.', [
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversa->id,
            'motivo' => $motivo,
        ]);
    }
}

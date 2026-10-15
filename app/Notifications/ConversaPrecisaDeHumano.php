<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversaPrecisaDeHumano extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversa,
        public readonly string $motivo,
        public readonly ?string $ultimaMensagem = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $telefone = explode('@', $this->conversa->chat_id)[0];

        return (new MailMessage())
            ->subject('Um cliente precisa de você no WhatsApp')
            ->greeting('Olá, '.$notifiable->firstName().'!')
            ->line("O atendimento automático parou a conversa com o número {$telefone}.")
            ->line('Motivo: '.$this->motivo)
            ->when($this->ultimaMensagem, fn (MailMessage $m) => $m->line('Última mensagem do cliente: "'.$this->ultimaMensagem.'"'))
            ->line('As mensagens já estão no WhatsApp do seu celular — responda por lá.');
    }
}

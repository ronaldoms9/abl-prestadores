<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A exportação grande de um relatório terminou; o link é assinado e vale 24 horas. */
class ExportacaoPronta extends Notification
{
    use Queueable;

    public function __construct(private readonly string $titulo, private readonly string $link, private readonly int $linhas)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Relatório \"{$this->titulo}\" pronto para baixar — ".config('app.name'))
            ->greeting('Olá!')
            ->line("O relatório \"{$this->titulo}\" ({$this->linhas} linha(s)) está pronto.")
            ->action('Baixar o arquivo', $this->link)
            ->line('O link vale por 24 horas e só funciona para quem pediu a exportação.');
    }
}

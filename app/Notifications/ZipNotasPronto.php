<?php

namespace App\Notifications;

use App\Models\Competencia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** O ZIP das notas da competência está pronto para baixar (link assinado, 24 horas). */
class ZipNotasPronto extends Notification
{
    use Queueable;

    public function __construct(private readonly Competencia $competencia, private readonly string $link, private readonly int $arquivos)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mes = substr($this->competencia->ano_mes, 5, 2).'/'.substr($this->competencia->ano_mes, 0, 4);

        return (new MailMessage)
            ->subject("Notas fiscais de {$mes} prontas para baixar — ".config('app.name'))
            ->greeting('Olá!')
            ->line("O arquivo ZIP com as notas de {$mes} ({$this->arquivos} arquivo(s)) está pronto.")
            ->action('Baixar o ZIP', $this->link)
            ->line('O link vale por 24 horas.');
    }
}

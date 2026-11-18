<?php

namespace App\Notifications;

use App\Models\NotaFiscal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Avisa o colaborador de que a nota foi recusada, com o motivo, e que pode reenviar. */
class NotaRecusada extends Notification
{
    use Queueable;

    public function __construct(private readonly NotaFiscal $nota)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mes = substr($this->nota->competencia->ano_mes, 5, 2).'/'.substr($this->nota->competencia->ano_mes, 0, 4);

        return (new MailMessage)
            ->subject("Nota fiscal de {$mes} recusada — ".config('app.name'))
            ->greeting('Olá!')
            ->line("A sua nota fiscal de {$mes} foi recusada.")
            ->line('Motivo: '.$this->nota->motivo_recusa)
            ->line('Corrija e envie a nota novamente pelo portal.')
            ->action('Abrir o portal', url('/portal/notas'));
    }
}

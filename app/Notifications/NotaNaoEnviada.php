<?php

namespace App\Notifications;

use App\Models\Competencia;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Lembrete ao colaborador: a nota da competência passou do prazo e ainda não foi enviada (ou foi recusada). */
class NotaNaoEnviada extends Notification
{
    use Queueable;

    public function __construct(private readonly Competencia $competencia, private readonly CarbonInterface $prazo)
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
            ->subject("Envie a sua nota fiscal de {$mes} — ".config('app.name'))
            ->greeting('Olá!')
            ->line("O prazo para enviar a nota fiscal de {$mes} era {$this->prazo->format('d/m/Y')} e ela ainda não consta como válida no sistema.")
            ->line('Se a nota foi recusada, o motivo aparece no portal.')
            ->action('Abrir o portal', url('/portal/notas'));
    }
}

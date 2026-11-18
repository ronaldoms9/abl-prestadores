<?php

namespace App\Notifications;

use App\Models\FeriasSolicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso aos aprovadores do nível de que há uma solicitação de férias esperando decisão (E4, fatia 3). */
class FeriasSolicitada extends Notification
{
    use Queueable;

    public function __construct(private readonly FeriasSolicitacao $solicitacao)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->solicitacao->loadMissing('colaborador.pessoaFisica');

        return (new MailMessage)
            ->subject('Férias para decidir: '.$s->colaborador->pessoaFisica->nome.' — '.config('app.name'))
            ->greeting('Olá!')
            ->line($s->colaborador->pessoaFisica->nome.' tem uma solicitação de férias esperando a sua decisão.')
            ->line('Período: '.$s->inicio->format('d/m/Y').' a '.$s->fim->format('d/m/Y').' ('.$s->dias.' dia(s)).')
            ->action('Ver solicitações', route('ferias.index'));
    }
}

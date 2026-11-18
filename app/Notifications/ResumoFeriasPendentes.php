<?php

namespace App\Notifications;

use App\Models\FeriasSolicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Lembrete diário das solicitações de férias que esperam a decisão do usuário (E4, fatia 3). */
class ResumoFeriasPendentes extends Notification
{
    use Queueable;

    private const LIMITE = 30;

    /** @param  Collection<int, FeriasSolicitacao>  $solicitacoes */
    public function __construct(private readonly Collection $solicitacoes)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject(sprintf('Férias esperando a sua decisão: %d — %s', $this->solicitacoes->count(), config('app.name')))
            ->greeting('Olá!')
            ->line('Estas solicitações de férias esperam a sua decisão:');

        foreach ($this->solicitacoes->take(self::LIMITE) as $s) {
            $mensagem->line(sprintf('• %s: %s a %s (%d dia(s))', $s->colaborador->pessoaFisica->nome, $s->inicio->format('d/m/Y'), $s->fim->format('d/m/Y'), $s->dias));
        }
        if ($this->solicitacoes->count() > self::LIMITE) {
            $mensagem->line('… e mais '.($this->solicitacoes->count() - self::LIMITE).'.');
        }

        return $mensagem->action('Decidir', route('ferias.index'));
    }
}

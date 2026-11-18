<?php

namespace App\Notifications;

use App\Enums\StatusSolicitacaoFerias;
use App\Models\FeriasSolicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso ao solicitante de que as férias foram aprovadas ou recusadas (E4, fatia 3). */
class FeriasDecidida extends Notification
{
    use Queueable;

    public function __construct(private readonly FeriasSolicitacao $solicitacao, private readonly ?string $observacao = null)
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
        $resultado = $s->status === StatusSolicitacaoFerias::Aprovada ? 'aprovadas' : 'recusadas';

        $mensagem = (new MailMessage)
            ->subject('Férias '.$resultado.': '.$s->colaborador->pessoaFisica->nome.' — '.config('app.name'))
            ->greeting('Olá!')
            ->line('As férias de '.$s->colaborador->pessoaFisica->nome.' de '.$s->inicio->format('d/m/Y').' a '.$s->fim->format('d/m/Y').' foram '.$resultado.'.');

        if ($this->observacao) {
            $mensagem->line('Observação: '.$this->observacao);
        }

        return $mensagem->action('Ver solicitações', route('ferias.index'));
    }
}

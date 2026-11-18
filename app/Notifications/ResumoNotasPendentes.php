<?php

namespace App\Notifications;

use App\Models\Competencia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Resumo diário para o financeiro: colaboradores com nota atrasada, por competência. */
class ResumoNotasPendentes extends Notification
{
    use Queueable;

    private const LIMITE = 40;

    /** @param  Collection<int, array{competencia: Competencia, nomes: list<string>}>  $grupos */
    public function __construct(private readonly Collection $grupos)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->grupos->sum(fn ($g) => count($g['nomes']));
        $mensagem = (new MailMessage)->subject("Notas fiscais atrasadas: {$total} — ".config('app.name'))->greeting('Olá!');

        foreach ($this->grupos as $g) {
            $mes = substr($g['competencia']->ano_mes, 5, 2).'/'.substr($g['competencia']->ano_mes, 0, 4);
            $mensagem->line("Competência {$mes}: ".count($g['nomes']).' sem nota válida.');
            foreach (array_slice($g['nomes'], 0, self::LIMITE) as $nome) {
                $mensagem->line('• '.$nome);
            }
            if (count($g['nomes']) > self::LIMITE) {
                $mensagem->line('… e mais '.(count($g['nomes']) - self::LIMITE).'.');
            }
        }

        return $mensagem->action('Ver notas', route('nota.index'));
    }
}

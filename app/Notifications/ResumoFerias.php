<?php

namespace App\Notifications;

use App\Models\FeriasPeriodo;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Um resumo por dia dos saldos de férias vencidos e perto do limite de gozo (E4, fatia 2). */
class ResumoFerias extends Notification
{
    use Queueable;

    private const LIMITE_POR_LISTA = 40;

    /**
     * @param  Collection<int, FeriasPeriodo>  $vencidos
     * @param  Collection<int, FeriasPeriodo>  $aVencer
     */
    public function __construct(
        private readonly Collection $vencidos,
        private readonly Collection $aVencer,
        private readonly int $dias,
        private readonly CarbonInterface $hoje,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject(sprintf('Férias: %d com limite vencido e %d perto do limite — %s', $this->vencidos->count(), $this->aVencer->count(), config('app.name')))
            ->greeting('Olá!')
            ->line("Resumo dos saldos de férias em {$this->hoje->format('d/m/Y')}.");

        $this->lista($mensagem, 'Limite de gozo vencido', $this->vencidos);
        $this->lista($mensagem, "Limite de gozo em até {$this->dias} dias", $this->aVencer);

        return $mensagem
            ->line('Registrar as férias do período (ou ajustar o limite) tira o colaborador desta lista.')
            ->action('Abrir o sistema', url('/'));
    }

    private function lista(MailMessage $mensagem, string $titulo, Collection $periodos): void
    {
        if ($periodos->isEmpty()) {
            return;
        }

        $mensagem->line("**{$titulo} ({$periodos->count()})**");

        foreach ($periodos->take(self::LIMITE_POR_LISTA) as $p) {
            $mensagem->line(sprintf('- %s · período %s a %s · saldo %d dia(s) · limite %s', $p->colaborador->pessoaFisica->nome, $p->aquisitivo_inicio->format('d/m/Y'), $p->aquisitivo_fim->format('d/m/Y'), $p->saldo(), $p->limite_gozo->format('d/m/Y')));
        }

        if ($periodos->count() > self::LIMITE_POR_LISTA) {
            $mensagem->line('… e mais '.($periodos->count() - self::LIMITE_POR_LISTA).'.');
        }
    }
}

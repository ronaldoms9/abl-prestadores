<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Um resumo por dia dos contratos vencidos e dos que chegaram a um marco de vencimento (E3, fatia 4). */
class ResumoContratos extends Notification
{
    use Queueable;

    private const LIMITE_POR_LISTA = 40;

    /**
     * @param  Collection<int, array{contrato: \App\Models\Contrato, fim: CarbonInterface, dias: int}>  $vencidos
     * @param  Collection<int, array{contrato: \App\Models\Contrato, fim: CarbonInterface, dias: int}>  $marcos
     */
    public function __construct(
        private readonly Collection $vencidos,
        private readonly Collection $marcos,
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
            ->subject(sprintf('Contratos: %d vencido(s) e %d a vencer — %s', $this->vencidos->count(), $this->marcos->count(), config('app.name')))
            ->greeting('Olá!')
            ->line("Resumo dos contratos em {$this->hoje->format('d/m/Y')}.");

        $this->lista($mensagem, 'Vencidos', $this->vencidos);
        $this->lista($mensagem, 'Vencem em breve', $this->marcos);

        return $mensagem
            ->line('Para prorrogar, abra o colaborador e gere o aditivo de renovação. Contrato renovado ou encerrado sai desta lista.')
            ->action('Abrir o sistema', url('/'));
    }

    private function lista(MailMessage $mensagem, string $titulo, Collection $itens): void
    {
        if ($itens->isEmpty()) {
            return;
        }

        $mensagem->line("**{$titulo} ({$itens->count()})**");

        foreach ($itens->take(self::LIMITE_POR_LISTA) as $item) {
            $situacao = match (true) {
                $item['dias'] < 0 => 'venceu há '.abs($item['dias']).' dia(s)',
                $item['dias'] === 0 => 'vence hoje',
                default => 'vence em '.$item['dias'].' dia(s)',
            };

            $mensagem->line(sprintf('- %s · contrato %s · até %s (%s)', $item['contrato']->colaborador->pessoaFisica->nome, $item['contrato']->numero, $item['fim']->format('d/m/Y'), $situacao));
        }

        if ($itens->count() > self::LIMITE_POR_LISTA) {
            $mensagem->line('… e mais '.($itens->count() - self::LIMITE_POR_LISTA).'.');
        }
    }
}

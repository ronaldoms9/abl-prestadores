<?php

namespace App\Notifications;

use App\Models\Colaborador;
use App\Models\Documento;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Um resumo por dia, com todos os documentos vencidos e a vencer, em vez de um
 * e-mail por documento (E2, fatia 5).
 */
class ResumoDocumentos extends Notification
{
    use Queueable;

    private const LIMITE_POR_LISTA = 40;

    /**
     * @param  Collection<int, Documento>  $vencidos
     * @param  Collection<int, Documento>  $aVencer
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
            ->subject(sprintf('Documentos: %d vencido(s) e %d a vencer — %s', $this->vencidos->count(), $this->aVencer->count(), config('app.name')))
            ->greeting('Olá!')
            ->line("Resumo dos documentos em {$this->hoje->format('d/m/Y')}.");

        $this->lista($mensagem, 'Vencidos', $this->vencidos);
        $this->lista($mensagem, "Vencem em até {$this->dias} dias", $this->aVencer);

        return $mensagem
            ->line('Ao anexar a renovação de um documento, ele sai desta lista.')
            ->action('Abrir o sistema', url('/'));
    }

    private function lista(MailMessage $mensagem, string $titulo, Collection $documentos): void
    {
        if ($documentos->isEmpty()) {
            return;
        }

        $mensagem->line("**{$titulo} ({$documentos->count()})**");

        foreach ($documentos->take(self::LIMITE_POR_LISTA) as $documento) {
            $mensagem->line(sprintf('- %s · %s · %s', $this->dono($documento), $documento->tipo, $documento->validade->format('d/m/Y')));
        }

        if ($documentos->count() > self::LIMITE_POR_LISTA) {
            $mensagem->line('… e mais '.($documentos->count() - self::LIMITE_POR_LISTA).'.');
        }
    }

    private function dono(Documento $documento): string
    {
        $dono = $documento->documentavel;

        return match (true) {
            $dono instanceof Colaborador => $dono->pessoaFisica->nome.' (colaborador)',
            isset($dono->razao_social) => $dono->razao_social,
            default => $dono->nome ?? '—',
        };
    }
}

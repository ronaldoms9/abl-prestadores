<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Um resumo por dia das qualificações obrigatórias ausentes, vencidas e a vencer (E2, fatia 7). */
class ResumoQualificacoes extends Notification
{
    use Queueable;

    private const LIMITE_POR_LISTA = 40;

    /**
     * @param  Collection<int, array<string, mixed>>  $situacoes  Saída de SituacaoQualificacoes::analisar().
     */
    public function __construct(private readonly Collection $situacoes, private readonly int $dias, private readonly CarbonInterface $hoje)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function contar(Collection $situacoes, string $chave): int
    {
        return $situacoes->sum(fn (array $s) => $s[$chave]->count());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ausentes = self::contar($this->situacoes, 'ausentes');
        $vencidas = self::contar($this->situacoes, 'vencidas');
        $aVencer = self::contar($this->situacoes, 'a_vencer');

        $mensagem = (new MailMessage)
            ->subject(sprintf('Qualificações: %d ausente(s), %d vencida(s) e %d a vencer — %s', $ausentes, $vencidas, $aVencer, config('app.name')))
            ->greeting('Olá!')
            ->line("Resumo das qualificações obrigatórias em {$this->hoje->format('d/m/Y')}.");

        $this->lista($mensagem, "Ausentes ({$ausentes})", fn (array $s) => $s['ausentes']->map(fn ($tipo) => $tipo->nome.' (nunca registrada)'));
        $this->lista($mensagem, "Vencidas ({$vencidas})", fn (array $s) => $s['vencidas']->map(fn ($q) => $q->tipo->nome.' (venceu em '.$q->data_validade->format('d/m/Y').')'));
        $this->lista($mensagem, "Vencem em até {$this->dias} dias ({$aVencer})", fn (array $s) => $s['a_vencer']->map(fn ($q) => $q->tipo->nome.' ('.$q->data_validade->format('d/m/Y').')'));

        return $mensagem
            ->line('Ao registrar a renovação de uma qualificação, ela sai desta lista.')
            ->action('Abrir o sistema', url('/'));
    }

    private function lista(MailMessage $mensagem, string $titulo, callable $itens): void
    {
        $linhas = $this->situacoes
            ->flatMap(fn (array $s) => $itens($s)->map(fn (string $texto) => $s['colaborador']->pessoaFisica->nome.' · '.$texto));

        if ($linhas->isEmpty()) {
            return;
        }

        $mensagem->line("**{$titulo}**");
        foreach ($linhas->take(self::LIMITE_POR_LISTA) as $linha) {
            $mensagem->line('- '.$linha);
        }
        if ($linhas->count() > self::LIMITE_POR_LISTA) {
            $mensagem->line('… e mais '.($linhas->count() - self::LIMITE_POR_LISTA).'.');
        }
    }
}

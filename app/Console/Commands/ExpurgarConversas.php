<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Apaga conversas e mensagens vencidas, preservando metrics_daily.
 *
 * Métrica agregada não é dado pessoal e sobrevive ao expurgo — é o que mantém o
 * histórico de custo e volume do cliente depois dos 90 dias.
 */
class ExpurgarConversas extends Command
{
    protected $signature = 'conversas:expurgar {--dry-run : Apenas mostra o que seria apagado}';

    protected $description = 'Expurga conversas e mensagens com prazo de retenção vencido';

    public function handle(): int
    {
        $vencidas = Conversation::query()
            ->semEscopoDeTenant()
            ->whereNotNull('expurgo_em')
            ->where('expurgo_em', '<=', now());

        $total = (clone $vencidas)->count();

        if ($total === 0) {
            $this->info('Nada a expurgar.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$total} conversa(s) seriam apagadas.");

            return self::SUCCESS;
        }

        $apagadas = 0;

        (clone $vencidas)->chunkById(200, function ($conversas) use (&$apagadas) {
            $ids = $conversas->pluck('id');

            Message::query()->semEscopoDeTenant()->whereIn('conversation_id', $ids)->delete();
            Conversation::query()->semEscopoDeTenant()->whereIn('id', $ids)->delete();

            $apagadas += $ids->count();
        });

        $this->info("{$apagadas} conversa(s) expurgadas; métricas preservadas.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Integrations\Uau\Jobs;

use App\Enums\StatusSincronizacao;
use App\Models\Sincronizacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Último elo da cadeia de conciliação: marca a sincronização como concluída (D8.2 da fatia 8). */
class ConcluirSincronizacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $sincronizacaoId)
    {
        $this->onQueue('uau');
    }

    public function handle(): void
    {
        $sincronizacao = Sincronizacao::find($this->sincronizacaoId);

        if ($sincronizacao?->status === StatusSincronizacao::EmAndamento) {
            $sincronizacao->concluir();
        }
    }
}

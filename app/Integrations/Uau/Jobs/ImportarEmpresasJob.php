<?php

namespace App\Integrations\Uau\Jobs;

use App\Actions\Uau\ImportarEmpresas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportarEmpresasJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrataFalhaDoUau;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('uau');
    }

    public function uniqueId(): string
    {
        return 'empresas';
    }

    public function handle(ImportarEmpresas $action): void
    {
        $this->executar(function () use ($action) {
            $resumo = $action->executar(tentativa: $this->attempts());

            Log::info("ImportarEmpresasJob: {$resumo}");
        });
    }
}

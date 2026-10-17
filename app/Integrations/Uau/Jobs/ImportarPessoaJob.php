<?php

namespace App\Integrations\Uau\Jobs;

use App\Actions\Uau\ImportarPessoa;
use App\Support\Documento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportarPessoaJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrataFalhaDoUau;

    public string $documento;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(string $documento)
    {
        $this->documento = Documento::digitos($documento) ?? $documento;
        $this->onQueue('uau');
    }

    public function uniqueId(): string
    {
        return "pessoa:{$this->documento}";
    }

    public function handle(ImportarPessoa $action): void
    {
        $this->executar(function () use ($action) {
            $resultado = $action->executar($this->documento, tentativa: $this->attempts());

            Log::info("ImportarPessoaJob: pessoa {$this->documento} - {$resultado->resultado->rotulo()}");
        });
    }
}

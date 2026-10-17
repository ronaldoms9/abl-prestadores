<?php

namespace App\Integrations\Uau\Jobs;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConciliarPessoasJob implements ShouldQueue, ShouldBeUnique
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
        return 'conciliar-pessoas';
    }

    public function handle(): void
    {
        $this->executar(function () {
            $totalPf = 0;
            $totalPj = 0;

            PessoaFisica::query()->lazyById(100)->each(function (PessoaFisica $pf) use (&$totalPf) {
                ImportarPessoaJob::dispatch($pf->cpf);
                $totalPf++;
            });

            PessoaJuridica::query()->lazyById(100)->each(function (PessoaJuridica $pj) use (&$totalPj) {
                ImportarPessoaJob::dispatch($pj->cnpj);
                $totalPj++;
            });

            Log::info("ConciliarPessoasJob: despachadas {$totalPf} PF e {$totalPj} PJ para conciliação.");
        });
    }
}

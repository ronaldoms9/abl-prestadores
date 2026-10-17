<?php

namespace App\Integrations\Uau\Jobs;

use App\Actions\Pessoas\ProcessarLinha;
use App\Enums\StatusImportacao;
use App\Models\ImportacaoPessoa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Processa um lote de linhas pendentes de uma carga de planilha e, se sobrar
 * linha, despacha a si mesmo de novo (D5b.5 da fatia 5b). Lotes deixam a
 * conciliação diária, na mesma fila, rodar entre um lote e outro.
 *
 * ShouldBeUniqueUntilProcessing: a trava sai quando o job começa, senão o
 * próprio job não conseguiria se despachar para o lote seguinte.
 */
class ProcessarImportacaoPessoasJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrataFalhaDoUau;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $importacaoId)
    {
        $this->onQueue('uau');
    }

    public function uniqueId(): string
    {
        return "importacao:{$this->importacaoId}";
    }

    public function handle(ProcessarLinha $processar): void
    {
        $importacao = ImportacaoPessoa::find($this->importacaoId);

        if ($importacao === null || ! $importacao->emAndamento()) {
            return;
        }

        $this->executar(function () use ($importacao, $processar) {
            if ($importacao->status === StatusImportacao::Pendente) {
                $importacao->update(['status' => StatusImportacao::Processando, 'iniciada_em' => now()]);
            }

            $importacao->linhas()->pendentes()->orderBy('linha')
                ->limit(max(1, (int) config('uau.planilha.lote')))
                ->get()
                ->each(fn ($linha) => $processar->executar($linha, $this->attempts()));

            if ($importacao->linhas()->pendentes()->exists()) {
                self::dispatch($importacao->id);

                return;
            }

            $importacao->update([
                'status' => StatusImportacao::Concluida,
                'concluida_em' => now(),
                'resumo' => $importacao->calcularResumo(),
            ]);

            Log::info("ProcessarImportacaoPessoasJob: carga {$importacao->id} concluída ({$importacao->total_linhas} linhas).");
        });
    }

    /** Tentativas esgotadas: a carga falha com o erro, e as linhas prontas ficam (D5b.6). */
    public function failed(?Throwable $erro): void
    {
        $importacao = ImportacaoPessoa::find($this->importacaoId);

        $importacao?->update([
            'status' => StatusImportacao::Falhou,
            'erro' => Str::limit($erro?->getMessage() ?? 'Falha desconhecida.', 2000, ''),
            'resumo' => $importacao->calcularResumo(),
        ]);
    }
}

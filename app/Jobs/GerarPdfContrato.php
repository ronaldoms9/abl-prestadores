<?php

namespace App\Jobs;

use App\Enums\StatusContrato;
use App\Models\Contrato;
use App\Support\Contratos\GeradorPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/** Gera o PDF do contrato já gerado e guarda no disco privado. Pode rodar de novo sem efeito colateral. */
class GerarPdfContrato implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $contratoId)
    {
    }

    public function handle(GeradorPdf $gerador): void
    {
        $contrato = Contrato::query()->find($this->contratoId);

        if ($contrato === null || $contrato->status === StatusContrato::Rascunho || $contrato->conteudo_renderizado === null) {
            return;
        }

        $bytes = $gerador->gerar(View::make('contratos.pdf', ['contrato' => $contrato])->render());

        $disco = Storage::disk(config('cadastros.contratos.disco'));
        $caminho = "contratos/{$contrato->ano}/{$contrato->id}/".str_replace('/', '-', $contrato->numero).'-'.Str::lower(Str::random(8)).'.pdf';
        $disco->put($caminho, $bytes);

        $antigo = $contrato->pdf_path;
        $contrato->update(['pdf_path' => $caminho]);

        if ($antigo !== null && $antigo !== $caminho) {
            $disco->delete($antigo);
        }
    }
}

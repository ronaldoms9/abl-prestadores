<?php

namespace App\Jobs;

use App\Enums\StatusNota;
use App\Models\Competencia;
use App\Models\NotaFiscal;
use App\Models\User;
use App\Notifications\ZipNotasPronto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

/** Junta os PDFs e XMLs das notas não recusadas da competência num ZIP e avisa quem pediu, com link assinado de 24 horas. */
class GerarZipNotas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $competenciaId, public readonly int $usuarioId)
    {
    }

    public function handle(): void
    {
        $competencia = Competencia::find($this->competenciaId);
        $usuario = User::find($this->usuarioId);
        if ($competencia === null || $usuario === null) {
            return;
        }

        $disco = Storage::disk(config('cadastros.notas.disco'));
        $nome = Str::uuid().'.zip';
        $disco->makeDirectory('notas-zip');
        $caminho = $disco->path('notas-zip/'.$nome);

        $this->limparAntigos($disco);

        $zip = new ZipArchive;
        if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP das notas.');
        }
        $arquivos = 0;

        NotaFiscal::query()
            ->with('colaborador.pessoaFisica')
            ->where('competencia_id', $competencia->id)
            ->where('status', '!=', StatusNota::Recusada->value)
            ->orderBy('id')
            ->each(function (NotaFiscal $nota) use ($zip, $disco, &$arquivos) {
                // O número é texto livre: só letras, números e hífen entram no nome do arquivo.
                $quem = Str::slug($nota->colaborador->pessoaFisica->nome).'-'.$nota->colaborador_id;
                $numero = Str::slug((string) $nota->numero) ?: 'sem-numero';
                foreach (['pdf' => $nota->pdf_path, 'xml' => $nota->xml_path] as $extensao => $origem) {
                    if ($origem !== null && $disco->exists($origem)) {
                        $zip->addFromString("{$quem}/nota-{$numero}.{$extensao}", $disco->get($origem));
                        $arquivos++;
                    }
                }
            });

        if ($arquivos === 0) {
            $zip->addFromString('LEIAME.txt', 'Nenhuma nota com arquivo nesta competência.');
        }
        $zip->close();

        $usuario->notify(new ZipNotasPronto($competencia, URL::temporarySignedRoute('nota.zip.baixar', now()->addDay(), ['arquivo' => $nome, 'usuario' => $usuario->id]), $arquivos));
    }

    /** ZIPs com mais de dois dias (o link vale um) não servem mais. */
    private function limparAntigos($disco): void
    {
        foreach ($disco->files('notas-zip') as $arquivo) {
            if ($disco->lastModified($arquivo) < now()->subDays(2)->getTimestamp()) {
                $disco->delete($arquivo);
            }
        }
    }
}

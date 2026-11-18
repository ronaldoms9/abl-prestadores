<?php

namespace App\Jobs;

use App\Exports\RelatorioExport;
use App\Models\User;
use App\Notifications\ExportacaoPronta;
use App\Services\Relatorios\Relatorios;
use App\Support\Contratos\GeradorPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as TipoExcel;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportação grande de um relatório, fora da requisição (E6, fatia 4). Gera com
 * os mesmos filtros e o mesmo escopo do usuário que pediu, guarda em disco
 * privado e avisa por e-mail com um link assinado de 24 horas, só dele.
 */
class ExportarRelatorio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param  array<string, mixed>  $filtros  já validados */
    public function __construct(public readonly string $slug, public readonly array $filtros, public readonly string $formato, public readonly int $usuarioId)
    {
    }

    public function handle(Relatorios $catalogo, GeradorPdf $pdf): void
    {
        $usuario = User::find($this->usuarioId);
        if ($usuario === null) {
            return;
        }

        $relatorio = $catalogo->achar($this->slug, $usuario);
        $resultado = $relatorio->executar($this->filtros, $usuario);

        $conteudo = $this->formato === 'xlsx'
            ? Excel::raw(new RelatorioExport($relatorio->titulo(), $resultado), TipoExcel::XLSX)
            : $pdf->gerar(View::make('relatorios.pdf', ['relatorio' => $relatorio, 'resultado' => $resultado, 'filtros' => [], 'emissao' => now()])->render());

        $disco = Storage::disk(config('cadastros.relatorios.disco'));
        $disco->makeDirectory('relatorios');
        $nome = Str::uuid().'.'.$this->formato;
        $disco->put('relatorios/'.$nome, $conteudo);

        foreach ($disco->files('relatorios') as $arquivo) {
            if ($disco->lastModified($arquivo) < now()->subDays(2)->getTimestamp()) {
                $disco->delete($arquivo);
            }
        }

        $usuario->notify(new ExportacaoPronta($relatorio->titulo(), URL::temporarySignedRoute('relatorio.arquivo', now()->addDay(), ['arquivo' => $nome, 'usuario' => $usuario->id]), count($resultado->linhas)));
    }
}

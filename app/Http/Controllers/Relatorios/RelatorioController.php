<?php

namespace App\Http\Controllers\Relatorios;

use App\Enums\StatusNota;
use App\Exports\RelatorioExport;
use App\Jobs\ExportarRelatorio;
use App\Http\Controllers\Controller;
use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\Empresa;
use App\Models\Equipe;
use App\Models\Obra;
use App\Services\Relatorios\Relatorio;
use App\Services\Relatorios\Relatorios;
use App\Services\Relatorios\ResultadoRelatorio;
use App\Support\Contratos\GeradorPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;

/** Os seis relatórios da proposta, com filtros, e a exportação em Excel e PDF (E6, fatias 2 e 3). */
class RelatorioController extends Controller
{
    public function index(Request $request, Relatorios $catalogo)
    {
        return view('relatorios.index', ['relatorios' => $catalogo->visiveisPara($request->user())]);
    }

    public function show(Request $request, Relatorios $catalogo, string $slug)
    {
        $relatorio = $catalogo->achar($slug, $request->user());
        $filtros = $this->filtros($request, $relatorio);

        return view('relatorios.show', [
            'relatorio' => $relatorio,
            'filtros' => $filtros,
            'resultado' => $relatorio->executar($filtros, $request->user()),
            'opcoes' => $this->opcoes($relatorio),
        ]);
    }

    public function exportar(Request $request, Relatorios $catalogo, string $slug, string $formato)
    {
        abort_unless(in_array($formato, ['xlsx', 'pdf'], true), 404);
        $relatorio = $catalogo->achar($slug, $request->user());
        $filtros = $this->filtros($request, $relatorio);
        $resultado = $relatorio->executar($filtros, $request->user());

        // Volume grande: gera em segundo plano e avisa por e-mail, em vez de segurar a requisição.
        if (count($resultado->linhas) > (int) config('cadastros.relatorios.limite_sincrono')) {
            ExportarRelatorio::dispatch($relatorio->slug(), $filtros, $formato, $request->user()->id);

            return response()->json(['success' => true, 'message' => 'O relatório é grande e está sendo gerado. Você receberá um e-mail com o link para baixar quando ficar pronto.', 'data' => ['em_segundo_plano' => true]], 202);
        }

        $arquivo = $relatorio->slug().'-'.now()->format('Y-m-d');

        if ($formato === 'xlsx') {
            return Excel::download(new RelatorioExport($relatorio->titulo(), $resultado), $arquivo.'.xlsx');
        }

        try {
            $bytes = app(GeradorPdf::class)->gerar(View::make('relatorios.pdf', [
                'relatorio' => $relatorio, 'resultado' => $resultado, 'filtros' => $this->descricaoDosFiltros($relatorio, $filtros), 'emissao' => now(),
            ])->render());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => 'A geração de PDF ainda não está disponível neste ambiente (biblioteca não instalada). Use o Excel.', 'data' => null], 503);
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$arquivo.'.pdf"']);
    }

    /** Download do arquivo de uma exportação grande: link assinado, só de quem pediu. */
    public function arquivo(Request $request, string $arquivo)
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]+\.(xlsx|pdf)$/', $arquivo) === 1, 404);
        abort_unless((int) $request->query('usuario') === $request->user()->id, 403);

        $disco = \Illuminate\Support\Facades\Storage::disk(config('cadastros.relatorios.disco'));
        abort_unless($disco->exists('relatorios/'.$arquivo), 404);

        return $disco->download('relatorios/'.$arquivo, 'relatorio-'.now()->format('Y-m-d').'.'.pathinfo($arquivo, PATHINFO_EXTENSION));
    }

    /** @return array<string, mixed> */
    private function filtros(Request $request, Relatorio $relatorio): array
    {
        $regras = [];
        foreach ($relatorio->filtros() as $nome => [, $tipo]) {
            $regras[$nome] = match ($tipo) {
                'mes' => ['nullable', 'date_format:Y-m'],
                'numero' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'status' => ['nullable', 'in:pendentes,todas,'.implode(',', array_map(fn (StatusNota $s) => $s->value, StatusNota::cases()))],
                default => ['nullable', 'integer'],
            };
        }

        $validos = $request->validate($regras);

        $filtros = [];
        foreach ($relatorio->filtros() as $nome => [, , $padrao]) {
            $valor = $validos[$nome] ?? null;
            $filtros[$nome] = ($valor === null || $valor === '') ? $padrao : $valor;
        }

        return $filtros;
    }

    /** @return array<string, string> rótulo => valor legível, só dos filtros preenchidos */
    private function descricaoDosFiltros(Relatorio $relatorio, array $filtros): array
    {
        $opcoes = $this->opcoes($relatorio);
        $texto = [];

        foreach ($relatorio->filtros() as $nome => [$rotulo, $tipo]) {
            $valor = $filtros[$nome] ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }
            $texto[$rotulo] = (string) (($opcoes[$tipo] ?? [])[$valor] ?? $valor);
        }

        return $texto;
    }

    /** @return array<string, array<int|string, string>> */
    private function opcoes(Relatorio $relatorio): array
    {
        $tipos = array_column($relatorio->filtros(), 1);
        $lista = [];

        if (in_array('obra', $tipos, true)) {
            $lista['obra'] = Obra::query()->ativos()->orderBy('descricao')->get()->mapWithKeys(fn ($o) => [$o->id => ($o->codigo ? $o->codigo.' · ' : '').$o->descricao])->all();
        }
        if (in_array('empresa', $tipos, true)) {
            $lista['empresa'] = Empresa::query()->ativos()->orderBy('razao_social')->pluck('razao_social', 'id')->all();
        }
        if (in_array('equipe', $tipos, true)) {
            $lista['equipe'] = Equipe::query()->ativos()->orderBy('nome')->pluck('nome', 'id')->all();
        }
        if (in_array('cargo', $tipos, true)) {
            $lista['cargo'] = Cargo::query()->ativos()->orderBy('nome')->pluck('nome', 'id')->all();
        }
        if (in_array('beneficio', $tipos, true)) {
            $lista['beneficio'] = Beneficio::query()->orderBy('nome')->pluck('nome', 'id')->all();
        }
        if (in_array('status', $tipos, true)) {
            $lista['status'] = ['pendentes' => 'Pendentes (não aprovadas)', 'todas' => 'Todas'] + collect(StatusNota::cases())->mapWithKeys(fn (StatusNota $s) => [$s->value => $s->rotulo()])->all();
        }

        return $lista;
    }
}

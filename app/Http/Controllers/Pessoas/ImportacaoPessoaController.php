<?php

namespace App\Http\Controllers\Pessoas;

use App\Actions\Pessoas\CarregarPlanilha;
use App\Actions\Pessoas\PlanilhaInvalida;
use App\Enums\ResultadoImportacao;
use App\Exports\ModeloPlanilhaPessoas;
use App\Http\Controllers\Controller;
use App\Http\Requests\CarregarPlanilhaRequest;
use App\Models\ImportacaoPessoa;
use App\Models\ImportacaoPessoaLinha;
use App\Support\Documento;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Carga de pessoas por planilha (fatia 5b): upload, modelo, histórico e
 * resultado por linha. O processamento roda na fila uau.
 */
class ImportacaoPessoaController extends Controller
{
    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->historico();
        }

        $html = $builder->columns([
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Data', 'class' => 'text-nowrap'],
            ['data' => 'nome_original', 'name' => 'nome_original', 'title' => 'Arquivo'],
            ['data' => 'usuario', 'name' => 'usuario', 'title' => 'Carregada por', 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => 'Situação'],
            ['data' => 'total_linhas', 'name' => 'total_linhas', 'title' => 'Linhas'],
            ['data' => 'resumo', 'name' => 'resumo', 'title' => 'Resultado', 'orderable' => false],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'exportable' => false, 'class' => 'td-actions'],
        ])->ajax(route('importacao-pessoa.index'))->parameters([
            'searching' => false,
            'order' => [[0, 'desc']],
            'language' => ['url' => url('js/datatable.json')],
            'responsive' => true,
            'pageLength' => 10,
        ]);

        return view('pessoas.importacoes.index', [
            'html' => $html,
            'maxLinhas' => (int) config('uau.planilha.max_linhas'),
        ]);
    }

    public function modelo()
    {
        return Excel::download(new ModeloPlanilhaPessoas(), 'modelo-pessoas.xlsx');
    }

    public function store(CarregarPlanilhaRequest $request, CarregarPlanilha $carregar)
    {
        try {
            $importacao = $carregar->executar($request->file('arquivo'));
        } catch (PlanilhaInvalida $e) {
            return back()->withErrors(['arquivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('importacao-pessoa.show', $importacao->id)
            ->with('success', "Planilha recebida: {$importacao->total_linhas} linhas. As consultas ao UAU rodam em segundo plano.");
    }

    public function show(Request $request, Builder $builder, $id)
    {
        $importacao = ImportacaoPessoa::with('criadoPor')->findOrFail($id);

        if ($request->ajax()) {
            return $this->linhas($request, $importacao);
        }

        $html = $builder->columns([
            ['data' => 'linha', 'name' => 'linha', 'title' => 'Linha'],
            ['data' => 'cpf', 'name' => 'cpf', 'title' => 'CPF', 'class' => 'text-nowrap'],
            ['data' => 'resultado_pf', 'name' => 'resultado_pf', 'title' => 'Resultado do CPF'],
            ['data' => 'cnpj', 'name' => 'cnpj', 'title' => 'CNPJ', 'class' => 'text-nowrap'],
            ['data' => 'resultado_pj', 'name' => 'resultado_pj', 'title' => 'Resultado do CNPJ'],
            ['data' => 'mensagem', 'name' => 'mensagem', 'title' => 'Mensagem', 'orderable' => false],
        ])->ajaxWithForm(route('importacao-pessoa.show', $importacao->id), '#filtros-linhas')->parameters([
            'searching' => false,
            'order' => [[0, 'asc']],
            'language' => ['url' => url('js/datatable.json')],
            'responsive' => true,
            'pageLength' => 25,
        ]);

        return view('pessoas.importacoes.show', [
            'html' => $html,
            'importacao' => $importacao,
            'resumo' => $importacao->resumoAtual(),
            'resultados' => ResultadoImportacao::cases(),
        ]);
    }

    private function historico()
    {
        $query = ImportacaoPessoa::query()->with('criadoPor');

        return DataTables::of($query)
            ->editColumn('created_at', fn (ImportacaoPessoa $importacao) => $importacao->created_at->format('d/m/Y H:i'))
            ->addColumn('usuario', fn (ImportacaoPessoa $importacao) => $importacao->criadoPor?->name ?? '—')
            ->editColumn('status', fn (ImportacaoPessoa $importacao) => $importacao->status->rotulo())
            ->addColumn('resumo', fn (ImportacaoPessoa $importacao) => self::resumoCurto($importacao->resumoAtual()))
            ->addColumn('action', fn (ImportacaoPessoa $importacao) => view('pessoas.importacoes.action', ['importacao' => $importacao]))
            ->rawColumns(['action'])
            ->make(true);
    }

    private function linhas(Request $request, ImportacaoPessoa $importacao)
    {
        // Sem o parâmetro, a tela abre só com as linhas que pedem atenção.
        $soProblemas = $request->boolean('problemas', ! $request->exists('problemas'));

        $query = $importacao->linhas()->getQuery()->when($soProblemas, fn ($q) => $q->comProblema());

        $resultado = fn (ImportacaoPessoaLinha $linha, string $campo, string $documento) => $linha->{$campo}?->rotulo()
            ?? ($linha->{$documento} !== null ? 'Não processada' : '—');

        return DataTables::of($query)
            ->editColumn('cpf', fn (ImportacaoPessoaLinha $linha) => Documento::formatar($linha->cpf) ?? '—')
            ->editColumn('cnpj', fn (ImportacaoPessoaLinha $linha) => Documento::formatar($linha->cnpj) ?? '—')
            ->editColumn('resultado_pf', fn (ImportacaoPessoaLinha $linha) => $resultado($linha, 'resultado_pf', 'cpf'))
            ->editColumn('resultado_pj', fn (ImportacaoPessoaLinha $linha) => $resultado($linha, 'resultado_pj', 'cnpj'))
            ->editColumn('mensagem', fn (ImportacaoPessoaLinha $linha) => $linha->mensagem ?? '')
            ->make(true);
    }

    /** "Importada: 3 · Inválida: 1", somando CPF e CNPJ. */
    public static function resumoCurto(array $resumo): string
    {
        $totais = [];
        foreach (['pf', 'pj'] as $tipo) {
            foreach ($resumo[$tipo] ?? [] as $resultado => $total) {
                $totais[$resultado] = ($totais[$resultado] ?? 0) + $total;
            }
        }

        $partes = [];
        foreach (ResultadoImportacao::cases() as $caso) {
            if (($totais[$caso->value] ?? 0) > 0) {
                $partes[] = $caso->rotulo().': '.$totais[$caso->value];
            }
        }

        if (($resumo['nao_processadas'] ?? 0) > 0) {
            $partes[] = 'Não processada: '.$resumo['nao_processadas'];
        }

        return $partes === [] ? '—' : implode(' · ', $partes);
    }
}

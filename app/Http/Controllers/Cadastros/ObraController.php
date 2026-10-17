<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Obra;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta de obras do UAU, só leitura (fatia 7). A obra faz o papel de centro de custo. */
class ObraController extends Controller
{
    use FiltraCadastro;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'empresa', 'name' => 'empresa', 'title' => 'Empresa', 'orderable' => false],
            ['data' => 'uau_codigo', 'name' => 'uau_codigo', 'title' => 'Código'],
            ['data' => 'descricao', 'name' => 'descricao', 'title' => 'Descrição'],
            ['data' => 'status_uau', 'name' => 'status_uau', 'title' => 'Status UAU'],
            ['data' => 'data_inicio', 'name' => 'data_inicio', 'title' => 'Início', 'class' => 'text-nowrap'],
            ['data' => 'data_fim', 'name' => 'data_fim', 'title' => 'Fim', 'class' => 'text-nowrap'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('obra.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[2, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Obras',
            'placeholder' => 'Descrição ou código da obra',
            'html' => $html,
            'filtrosExtras' => 'cadastros.obras._filtro-empresa',
            'empresas' => Empresa::orderBy('razao_social')->get(['id', 'uau_codigo', 'razao_social', 'ativo']),
        ]);
    }

    public function show($id)
    {
        return view('cadastros.obras.show', [
            'obra' => Obra::with(['empresa' => fn ($q) => $q->withTrashed()])->findOrFail($id),
        ]);
    }

    private function dados(Request $request)
    {
        $empresa = $request->validate(['empresa' => ['nullable', 'integer']])['empresa'] ?? null;

        $query = $this->filtrarCadastro(
            Obra::query()
                ->with(['empresa' => fn ($q) => $q->withTrashed()])
                ->when($empresa, fn ($q, $id) => $q->where('empresa_id', $id)),
            $request,
            null,
            ['descricao', 'uau_codigo'],
        );

        return DataTables::of($query)
            ->addColumn('empresa', fn (Obra $obra) => $obra->empresa ? "{$obra->empresa->uau_codigo} · {$obra->empresa->razao_social}" : '—')
            ->editColumn('status_uau', fn (Obra $obra) => $obra->statusRotulo())
            ->editColumn('data_inicio', fn (Obra $obra) => $obra->data_inicio?->format('d/m/Y') ?? '—')
            ->editColumn('data_fim', fn (Obra $obra) => $obra->data_fim?->format('d/m/Y') ?? '—')
            ->addColumn('situacao', fn (Obra $obra) => view('cadastros._situacao', ['ativo' => $obra->ativo]))
            ->addColumn('action', fn (Obra $obra) => view('cadastros._detalhes', ['url' => route('obra.show', $obra->id)]))
            ->rawColumns(['situacao', 'action'])
            ->only(['empresa', 'uau_codigo', 'descricao', 'status_uau', 'data_inicio', 'data_fim', 'situacao', 'action'])
            ->make(true);
    }
}

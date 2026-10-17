<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta de empresas do UAU, com as obras, só leitura (fatia 7). */
class EmpresaController extends Controller
{
    use FiltraCadastro;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'uau_codigo', 'name' => 'uau_codigo', 'title' => 'Código UAU'],
            ['data' => 'cnpj', 'name' => 'cnpj', 'title' => 'CNPJ', 'class' => 'text-nowrap'],
            ['data' => 'razao_social', 'name' => 'razao_social', 'title' => 'Razão social'],
            ['data' => 'obras_ativas_count', 'name' => 'obras_ativas_count', 'title' => 'Obras ativas', 'orderable' => false, 'searchable' => false],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('empresa.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[2, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Empresas',
            'placeholder' => 'Razão social ou CNPJ',
            'html' => $html,
        ]);
    }

    public function show($id)
    {
        return view('cadastros.empresas.show', [
            'empresa' => Empresa::with(['obras' => fn ($q) => $q->orderBy('uau_codigo')])->findOrFail($id),
        ]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(
            Empresa::query()->withCount(['obras as obras_ativas_count' => fn ($q) => $q->ativos()]),
            $request,
            'cnpj',
            ['razao_social'],
        );

        return DataTables::of($query)
            ->editColumn('cnpj', fn (Empresa $empresa) => $empresa->cnpj_formatado)
            ->addColumn('situacao', fn (Empresa $empresa) => view('cadastros._situacao', ['ativo' => $empresa->ativo]))
            ->addColumn('action', fn (Empresa $empresa) => view('cadastros._detalhes', ['url' => route('empresa.show', $empresa->id)]))
            ->rawColumns(['situacao', 'action'])
            ->only(['uau_codigo', 'cnpj', 'razao_social', 'obras_ativas_count', 'situacao', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\PessoaJuridica;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta de pessoas jurídicas, só leitura (fatia 7). */
class PessoaJuridicaController extends Controller
{
    use FiltraCadastro;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'cnpj', 'name' => 'cnpj', 'title' => 'CNPJ', 'class' => 'text-nowrap'],
            ['data' => 'razao_social', 'name' => 'razao_social', 'title' => 'Razão social'],
            ['data' => 'nome_fantasia', 'name' => 'nome_fantasia', 'title' => 'Nome fantasia'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'sincronizado_em', 'name' => 'sincronizado_em', 'title' => 'Sincronizado em', 'class' => 'text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('pessoa-juridica.index'), '#filtros-cadastro')->parameters($this->parametrosTabela());

        return $this->telaDeConsulta([
            'titulo' => 'Pessoas jurídicas',
            'placeholder' => 'Razão social, nome fantasia ou CNPJ',
            'html' => $html,
        ]);
    }

    public function show($id)
    {
        return view('cadastros.pessoas-juridicas.show', [
            'pessoa' => PessoaJuridica::with('pessoasFisicasSugeridas')->findOrFail($id),
        ]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(PessoaJuridica::query(), $request, 'cnpj', ['razao_social', 'nome_fantasia']);

        return DataTables::of($query)
            ->editColumn('cnpj', fn (PessoaJuridica $pessoa) => $pessoa->cnpj_formatado)
            ->editColumn('nome_fantasia', fn (PessoaJuridica $pessoa) => $pessoa->nome_fantasia ?? '—')
            ->addColumn('situacao', fn (PessoaJuridica $pessoa) => view('cadastros._situacao', ['ativo' => $pessoa->ativo]))
            ->editColumn('sincronizado_em', fn (PessoaJuridica $pessoa) => $pessoa->sincronizado_em?->format('d/m/Y H:i') ?? '—')
            ->addColumn('action', fn (PessoaJuridica $pessoa) => view('cadastros._detalhes', ['url' => route('pessoa-juridica.show', $pessoa->id)]))
            ->rawColumns(['situacao', 'action'])
            ->only(['cnpj', 'razao_social', 'nome_fantasia', 'situacao', 'sincronizado_em', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\PessoaFisica;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta de pessoas físicas, só leitura (fatia 7). */
class PessoaFisicaController extends Controller
{
    use FiltraCadastro;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'cpf', 'name' => 'cpf', 'title' => 'CPF', 'class' => 'text-nowrap'],
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Nome'],
            ['data' => 'email', 'name' => 'email', 'title' => 'E-mail'],
            ['data' => 'telefone', 'name' => 'telefone', 'title' => 'Telefone', 'orderable' => false],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'sincronizado_em', 'name' => 'sincronizado_em', 'title' => 'Sincronizado em', 'class' => 'text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('pessoa-fisica.index'), '#filtros-cadastro')->parameters($this->parametrosTabela());

        return $this->telaDeConsulta([
            'titulo' => 'Pessoas físicas',
            'placeholder' => 'Nome ou CPF, com ou sem máscara',
            'html' => $html,
        ]);
    }

    public function show($id)
    {
        return view('cadastros.pessoas-fisicas.show', [
            'pessoa' => PessoaFisica::with('pessoasJuridicasSugeridas')->findOrFail($id),
        ]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(PessoaFisica::query(), $request, 'cpf', ['nome']);

        return DataTables::of($query)
            ->editColumn('cpf', fn (PessoaFisica $pessoa) => $pessoa->cpf_formatado)
            ->editColumn('email', fn (PessoaFisica $pessoa) => $pessoa->email ?? '—')
            ->editColumn('telefone', fn (PessoaFisica $pessoa) => $pessoa->telefone ?? '—')
            ->addColumn('situacao', fn (PessoaFisica $pessoa) => view('cadastros._situacao', ['ativo' => $pessoa->ativo]))
            ->editColumn('sincronizado_em', fn (PessoaFisica $pessoa) => $pessoa->sincronizado_em?->format('d/m/Y H:i') ?? '—')
            ->addColumn('action', fn (PessoaFisica $pessoa) => view('cadastros._detalhes', ['url' => route('pessoa-fisica.show', $pessoa->id)]))
            ->rawColumns(['situacao', 'action'])
            ->only(['cpf', 'nome', 'email', 'telefone', 'situacao', 'sincronizado_em', 'action'])
            ->make(true);
    }
}

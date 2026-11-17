<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\AdmissaoChecklistItem;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Itens configuráveis do checklist de admissão (E3, fatia 5). Item automático (com chave) não se exclui nem muda de regra. */
class AdmissaoChecklistItemController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'ordem', 'name' => 'ordem', 'title' => 'Ordem', 'class' => 'text-end'],
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Item'],
            ['data' => 'origem', 'name' => 'chave', 'title' => 'Marcado por', 'orderable' => false],
            ['data' => 'obrigatorio', 'name' => 'obrigatorio', 'title' => 'Obrigatório'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('admissao-item.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Checklist de admissão',
            'placeholder' => 'Nome do item',
            'aviso' => 'Itens automáticos são marcados pelo sistema a partir do cadastro. Mudar a lista vale para as próximas admissões e para as que ainda não foram iniciadas; as já iniciadas guardam a cópia.',
            'html' => $html,
            'novo' => ['rota' => route('admissao-item.form'), 'rotulo' => 'Novo item'],
        ]);
    }

    public function form($id = 0)
    {
        return view('admissao.item-form', ['item' => $id ? AdmissaoChecklistItem::findOrFail($id) : new AdmissaoChecklistItem(['obrigatorio' => true, 'ordem' => 100])]);
    }

    public function store(Request $request, $id = 0)
    {
        $item = $id ? AdmissaoChecklistItem::findOrFail($id) : new AdmissaoChecklistItem;

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:255'],
            'obrigatorio' => ['nullable', 'boolean'],
            'ordem' => ['required', 'integer', 'min:0', 'max:9999'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome do item', 'ordem' => 'ordem']);

        $item->fill([
            'nome' => trim($validos['nome']),
            'obrigatorio' => $validos['obrigatorio'] ?? false,
            'ordem' => $validos['ordem'],
            'ativo' => $validos['ativo'] ?? $item->ativo ?? true,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Item salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $item = AdmissaoChecklistItem::findOrFail($id);

        if ($item->automatico()) {
            return response()->json(['success' => false, 'message' => 'Item automático não se exclui. Desative-o ou deixe-o opcional.', 'data' => null], 400);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Item excluído.', 'data' => null], 201);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(AdmissaoChecklistItem::query(), $request, null, ['nome']);

        return DataTables::of($query)
            ->addColumn('origem', fn (AdmissaoChecklistItem $i) => $i->automatico() ? 'Sistema' : 'RH')
            ->editColumn('obrigatorio', fn (AdmissaoChecklistItem $i) => $i->obrigatorio ? 'Sim' : 'Não')
            ->addColumn('situacao', fn (AdmissaoChecklistItem $i) => view('cadastros._situacao', ['ativo' => $i->ativo]))
            ->addColumn('action', fn (AdmissaoChecklistItem $i) => view('admissao._item-acoes', ['item' => $i]))
            ->rawColumns(['situacao', 'action'])
            ->only(['ordem', 'nome', 'origem', 'obrigatorio', 'situacao', 'action'])
            ->make(true);
    }
}

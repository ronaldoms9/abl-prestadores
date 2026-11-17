<?php

namespace App\Http\Controllers\Cadastros;

use App\Enums\AplicaDocumento;
use App\Http\Controllers\Controller;
use App\Models\DocumentoTipo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Tipos de documento configuráveis: a quem servem e se exigem validade (E2, fatia 5). */
class DocumentoTipoController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Tipo de documento'],
            ['data' => 'aplica_a', 'name' => 'aplica_a', 'title' => 'Vale para'],
            ['data' => 'validade', 'name' => 'exige_validade', 'title' => 'Validade'],
            ['data' => 'documentos', 'name' => 'documentos', 'title' => 'Anexados', 'orderable' => false, 'searchable' => false, 'class' => 'text-end'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('documento-tipo.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Tipos de documento',
            'placeholder' => 'Nome do tipo',
            'aviso' => 'Tipo que exige validade só aceita o anexo com a data. Tipo em uso não é excluído: desative-o.',
            'html' => $html,
            'novo' => ['rota' => route('documento-tipo.form'), 'rotulo' => 'Novo tipo'],
        ]);
    }

    public function form($id = 0)
    {
        return view('documento-tipos.form', ['tipo' => $id ? DocumentoTipo::findOrFail($id) : new DocumentoTipo(['aplica_a' => AplicaDocumento::Todos]), 'aplicacoes' => AplicaDocumento::cases()]);
    }

    public function store(Request $request, $id = 0)
    {
        $tipo = $id ? DocumentoTipo::findOrFail($id) : new DocumentoTipo;

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:255', Rule::unique('documento_tipos', 'nome')->ignore($tipo->id)],
            'aplica_a' => ['required', Rule::enum(AplicaDocumento::class)],
            'exige_validade' => ['nullable', 'boolean'],
            'obrigatorio_na_admissao' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome do tipo', 'aplica_a' => 'vale para']);

        $tipo->fill([
            'nome' => trim($validos['nome']),
            'aplica_a' => $validos['aplica_a'],
            'exige_validade' => $validos['exige_validade'] ?? false,
            'obrigatorio_na_admissao' => $validos['obrigatorio_na_admissao'] ?? false,
            'ativo' => $validos['ativo'] ?? $tipo->ativo ?? true,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Tipo de documento salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $tipo = DocumentoTipo::findOrFail($id);

        if ($tipo->documentos()->exists()) {
            return response()->json(['success' => false, 'message' => 'Há documentos deste tipo anexados. Desative o tipo em vez de excluir.', 'data' => null], 400);
        }

        $tipo->delete();

        return response()->json(['success' => true, 'message' => 'Tipo excluído.', 'data' => null], 201);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(DocumentoTipo::query(), $request, null, ['nome'])->withCount('documentos');

        return DataTables::of($query)
            ->editColumn('aplica_a', fn (DocumentoTipo $t) => $t->aplica_a->rotulo())
            ->addColumn('validade', fn (DocumentoTipo $t) => $t->exige_validade ? 'Obrigatória' : 'Sem validade')
            ->addColumn('documentos', fn (DocumentoTipo $t) => $t->documentos_count)
            ->addColumn('situacao', fn (DocumentoTipo $t) => view('cadastros._situacao', ['ativo' => $t->ativo]))
            ->addColumn('action', fn (DocumentoTipo $t) => view('documento-tipos._acoes', ['tipo' => $t]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'aplica_a', 'validade', 'documentos', 'situacao', 'action'])
            ->make(true);
    }
}

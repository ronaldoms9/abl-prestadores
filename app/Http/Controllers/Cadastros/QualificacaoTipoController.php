<?php

namespace App\Http\Controllers\Cadastros;

use App\Enums\CategoriaQualificacao;
use App\Http\Controllers\Controller;
use App\Models\CargoQualificacao;
use App\Models\QualificacaoTipo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Tipos de qualificação: categoria e validade em meses (E2, fatia 7). */
class QualificacaoTipoController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Qualificação'],
            ['data' => 'categoria', 'name' => 'categoria', 'title' => 'Categoria'],
            ['data' => 'validade', 'name' => 'validade_meses', 'title' => 'Validade'],
            ['data' => 'registros', 'name' => 'registros', 'title' => 'Registradas', 'orderable' => false, 'searchable' => false, 'class' => 'text-end'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('qualificacao-tipo.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Tipos de qualificação',
            'placeholder' => 'Nome da qualificação',
            'aviso' => 'A validade em meses é contada a partir da conclusão. Sem validade, a qualificação não vence. O que cada cargo exige fica em Matriz por cargo.',
            'html' => $html,
            'novo' => ['rota' => route('qualificacao-tipo.form'), 'rotulo' => 'Novo tipo'],
        ]);
    }

    public function form($id = 0)
    {
        return view('qualificacao-tipos.form', [
            'tipo' => $id ? QualificacaoTipo::findOrFail($id) : new QualificacaoTipo(['categoria' => CategoriaQualificacao::Curso]),
            'categorias' => CategoriaQualificacao::cases(),
        ]);
    }

    public function store(Request $request, $id = 0)
    {
        $tipo = $id ? QualificacaoTipo::findOrFail($id) : new QualificacaoTipo;

        $dados = $request->all();
        $dados['validade_meses'] = ($dados['validade_meses'] ?? '') === '' ? null : $dados['validade_meses'];

        $validos = $this->validarFormulario($request, $dados, [
            'nome' => ['required', 'string', 'max:255', Rule::unique('qualificacao_tipos', 'nome')->ignore($tipo->id)],
            'categoria' => ['required', Rule::enum(CategoriaQualificacao::class)],
            'validade_meses' => ['nullable', 'integer', 'min:1', 'max:600'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome da qualificação', 'categoria' => 'categoria', 'validade_meses' => 'validade em meses']);

        $tipo->fill([
            'nome' => trim($validos['nome']),
            'categoria' => $validos['categoria'],
            'validade_meses' => $validos['validade_meses'] ?? null,
            'ativo' => $validos['ativo'] ?? $tipo->ativo ?? true,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Tipo de qualificação salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $tipo = QualificacaoTipo::findOrFail($id);

        if ($tipo->qualificacoes()->exists() || CargoQualificacao::where('qualificacao_tipo_id', $tipo->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Este tipo tem registros ou está na matriz de algum cargo. Desative-o em vez de excluir.', 'data' => null], 400);
        }

        $tipo->delete();

        return response()->json(['success' => true, 'message' => 'Tipo excluído.', 'data' => null], 201);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(QualificacaoTipo::query(), $request, null, ['nome'])->withCount('qualificacoes');

        return DataTables::of($query)
            ->editColumn('categoria', fn (QualificacaoTipo $t) => $t->categoria->rotulo())
            ->addColumn('validade', fn (QualificacaoTipo $t) => $t->validade_meses ? $t->validade_meses.' meses' : 'Não vence')
            ->addColumn('registros', fn (QualificacaoTipo $t) => $t->qualificacoes_count)
            ->addColumn('situacao', fn (QualificacaoTipo $t) => view('cadastros._situacao', ['ativo' => $t->ativo]))
            ->addColumn('action', fn (QualificacaoTipo $t) => view('qualificacao-tipos._acoes', ['tipo' => $t]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'categoria', 'validade', 'registros', 'situacao', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers\Contratos;

use App\Actions\Contratos\SalvarContratoModelo;
use App\Enums\MotivoAditivo;
use App\Enums\TipoContrato;
use App\Http\Controllers\Cadastros\FiltraCadastro;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\ContratoModelo;
use App\Support\Contratos\ContratoVariaveis;
use App\Support\Contratos\RenderizadorContrato;
use App\Support\Contratos\SanitizadorHtml;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Modelos de contrato, aditivo e distrato, com variáveis e versões (E3, fatia 2). */
class ModeloController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Modelo'],
            ['data' => 'tipo', 'name' => 'tipo', 'title' => 'Tipo', 'orderable' => false],
            ['data' => 'versao', 'name' => 'versao', 'title' => 'Versão', 'class' => 'text-end'],
            ['data' => 'contratos', 'name' => 'contratos', 'title' => 'Contratos gerados', 'orderable' => false, 'searchable' => false, 'class' => 'text-end'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('contrato-modelo.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return view('cadastros.index', [
            'titulo' => 'Modelos de contrato',
            'placeholder' => 'Nome do modelo',
            'aviso' => 'Salvar um modelo que já gerou contratos cria uma nova versão; os contratos antigos continuam com a versão usada.',
            'html' => $html,
            'situacoes' => self::SITUACOES,
            'novo' => ['rota' => route('contrato-modelo.form'), 'rotulo' => 'Novo modelo', 'pagina' => true],
        ]);
    }

    public function form($id = 0)
    {
        $modelo = $id ? ContratoModelo::withCount('contratos')->findOrFail($id) : new ContratoModelo(['tipo' => TipoContrato::Contrato]);

        return view('contratos.modelos.form', [
            'modelo' => $modelo,
            'tipos' => TipoContrato::cases(),
            'motivos' => MotivoAditivo::cases(),
            'variaveis' => ContratoVariaveis::porGrupo(),
        ]);
    }

    public function store(Request $request, SalvarContratoModelo $salvar, $id = 0)
    {
        $modelo = $id ? ContratoModelo::findOrFail($id) : null;

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoContrato::class)],
            'motivo' => ['nullable', Rule::enum(MotivoAditivo::class)],
            'corpo_html' => ['required', 'string', 'max:500000'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome do modelo', 'corpo_html' => 'corpo do modelo']);

        try {
            $salvo = $salvar->executar($modelo, $validos);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        $versionou = $modelo !== null && $salvo->id !== $modelo->id;

        return response()->json([
            'success' => true,
            'message' => $versionou ? 'Modelo salvo como versão '.$salvo->versao.'.' : 'Modelo salvo.',
            'data' => ['id' => $salvo->id, 'redirect' => route('contrato-modelo.index')],
        ]);
    }

    /** Pré-visualização com dados de exemplo. O HTML volta limpo; a tela o mostra em um quadro isolado. */
    public function previa(Request $request, RenderizadorContrato $renderizador)
    {
        $validos = $this->validarFormulario($request, $request->all(), ['corpo_html' => ['required', 'string', 'max:500000']], ['corpo_html' => 'corpo do modelo']);

        $corpo = SanitizadorHtml::limpar($validos['corpo_html']);
        $desconhecidas = $renderizador->desconhecidas($corpo);

        return response()->json(['success' => true, 'message' => '', 'data' => [
            'html' => $renderizador->renderizar($corpo, ContratoVariaveis::exemplos())->html,
            'desconhecidas' => $desconhecidas,
        ]]);
    }

    public function delete($id)
    {
        $modelo = ContratoModelo::findOrFail($id);

        if ($modelo->contratos()->exists()) {
            return response()->json(['success' => false, 'message' => 'Este modelo já gerou contratos. Desative-o em vez de excluir.', 'data' => null], 400);
        }

        $modelo->delete();

        return response()->json(['success' => true, 'message' => 'Modelo excluído.', 'data' => null], 201);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(ContratoModelo::query(), $request, null, ['nome'])->withCount('contratos');

        return DataTables::of($query)
            ->addColumn('tipo', fn (ContratoModelo $m) => $m->tipo->rotulo())
            ->addColumn('contratos', fn (ContratoModelo $m) => $m->contratos_count)
            ->addColumn('situacao', fn (ContratoModelo $m) => view('cadastros._situacao', ['ativo' => $m->ativo]))
            ->addColumn('action', fn (ContratoModelo $m) => view('contratos.modelos._acoes', ['modelo' => $m]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'tipo', 'versao', 'contratos', 'situacao', 'action'])
            ->make(true);
    }
}

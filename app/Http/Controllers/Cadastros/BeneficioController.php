<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Beneficios\DefinirValorBeneficio;
use App\Enums\TipoCalculoBeneficio;
use App\Http\Controllers\Controller;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Tabela de benefícios e seus valores com vigência (E3, fatia 7). */
class BeneficioController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Benefício'],
            ['data' => 'tipo', 'name' => 'tipo_calculo', 'title' => 'Cálculo', 'orderable' => false],
            ['data' => 'valor_atual', 'name' => 'valor_atual', 'title' => 'Valor atual', 'orderable' => false, 'searchable' => false, 'class' => 'text-end text-nowrap'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('beneficio.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Benefícios',
            'placeholder' => 'Nome do benefício',
            'aviso' => 'O valor tem vigência: mudar o valor encerra o anterior e começa um novo. O valor de cada colaborador pode ser personalizado.',
            'html' => $html,
            'novo' => auth()->user()->can('beneficio-gerir') ? ['rota' => route('beneficio.form'), 'rotulo' => 'Novo benefício'] : null,
        ]);
    }

    public function form($id = 0)
    {
        return view('beneficios.form', [
            'beneficio' => $id ? Beneficio::findOrFail($id) : new Beneficio(['tipo_calculo' => TipoCalculoBeneficio::FixoMensal]),
            'tipos' => TipoCalculoBeneficio::cases(),
        ]);
    }

    public function store(Request $request, $id = 0)
    {
        $beneficio = $id ? Beneficio::findOrFail($id) : new Beneficio;

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:255', Rule::unique('beneficios', 'nome')->ignore($beneficio->id)],
            'tipo_calculo' => ['required', Rule::enum(TipoCalculoBeneficio::class)],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome do benefício', 'tipo_calculo' => 'tipo de cálculo']);

        $beneficio->fill([
            'nome' => trim($validos['nome']),
            'tipo_calculo' => $validos['tipo_calculo'],
            'ativo' => $validos['ativo'] ?? $beneficio->ativo ?? true,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Benefício salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $beneficio = Beneficio::findOrFail($id);

        if ($beneficio->vinculos()->exists()) {
            return response()->json(['success' => false, 'message' => 'Há colaboradores com este benefício. Desative o benefício em vez de excluir.', 'data' => null], 400);
        }

        $beneficio->delete();

        return response()->json(['success' => true, 'message' => 'Benefício excluído.', 'data' => null], 201);
    }

    /** Histórico de valores e o formulário do novo valor. */
    public function valores($id)
    {
        return view('beneficios.valores', [
            'beneficio' => Beneficio::with(['valores' => fn ($q) => $q->orderByDesc('vigencia_inicio')->orderByDesc('id')])->findOrFail($id),
        ]);
    }

    public function valorStore(Request $request, DefinirValorBeneficio $definir, $id)
    {
        $beneficio = Beneficio::findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'vigencia' => ['required', 'date'],
        ], ['valor' => 'valor do benefício', 'vigencia' => 'início da vigência']);

        try {
            $definir->executar($beneficio, $validos['valor'], Carbon::parse($validos['vigencia']));
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'Valor definido.', 'data' => []]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(Beneficio::query(), $request, null, ['nome'])
            ->with(['valores' => fn ($q) => $q->vigentes()]);

        return DataTables::of($query)
            ->addColumn('tipo', fn (Beneficio $b) => $b->tipo_calculo->rotulo())
            ->addColumn('valor_atual', function (Beneficio $b) {
                /** @var BeneficioValor|null $valor */
                $valor = $b->valores->first();

                return $valor ? Dinheiro::formatar(Dinheiro::centavos($valor->valor)) : '—';
            })
            ->addColumn('situacao', fn (Beneficio $b) => view('cadastros._situacao', ['ativo' => $b->ativo]))
            ->addColumn('action', fn (Beneficio $b) => view('beneficios._acoes', ['beneficio' => $b]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'tipo', 'valor_atual', 'situacao', 'action'])
            ->make(true);
    }
}

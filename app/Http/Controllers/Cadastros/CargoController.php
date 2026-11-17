<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Actions\Beneficios\SalvarBeneficiosPadraoCargo;
use App\Models\Beneficio;
use App\Models\Cargo;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Cargos com faixa de remuneração (E2, fatia 6). A faixa avisa, nunca bloqueia. */
class CargoController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Cargo'],
            ['data' => 'faixa', 'name' => 'faixa_min', 'title' => 'Faixa de remuneração', 'class' => 'text-nowrap', 'orderable' => false],
            ['data' => 'colaboradores', 'name' => 'colaboradores', 'title' => 'Colaboradores', 'orderable' => false, 'searchable' => false, 'class' => 'text-end'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('cargo.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Cargos',
            'placeholder' => 'Nome do cargo',
            'aviso' => 'A faixa de remuneração só gera aviso quando um valor sai dela; não bloqueia o reajuste.',
            'html' => $html,
            'novo' => auth()->user()->can('cargo-create') ? ['rota' => route('cargo.form'), 'rotulo' => 'Novo cargo'] : null,
        ]);
    }

    public function form($id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'cargo-edit' : 'cargo-create'), 403);

        $cargo = $id ? Cargo::findOrFail($id) : new Cargo;

        return view('cargos.form', [
            'cargo' => $cargo,
            'beneficios' => auth()->user()->can('beneficio-gerir') ? Beneficio::ativos()->orderBy('nome')->get() : collect(),
            'beneficiosPadrao' => $cargo->exists ? $cargo->beneficiosPadrao()->pluck('beneficio_id')->all() : [],
        ]);
    }

    public function store(Request $request, SalvarBeneficiosPadraoCargo $salvarBeneficios, $id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'cargo-edit' : 'cargo-create'), 403);
        $cargo = $id ? Cargo::findOrFail($id) : new Cargo;

        $dados = $request->all();
        foreach (['faixa_min', 'faixa_max'] as $campo) {
            $dados[$campo] = ($dados[$campo] ?? '') === '' ? null : $dados[$campo];
        }

        $validos = $this->validarFormulario($request, $dados, [
            'nome' => ['required', 'string', 'max:255', Rule::unique('cargos', 'nome')->ignore($cargo->id)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'faixa_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'faixa_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'gte:faixa_min'],
            'ativo' => ['nullable', 'boolean'],
            'beneficios_padrao' => ['nullable', 'array'],
            'beneficios_padrao.*' => ['integer'],
        ], ['nome' => 'nome do cargo', 'descricao' => 'descrição', 'faixa_min' => 'faixa mínima', 'faixa_max' => 'faixa máxima']);

        $cargo->fill([
            'nome' => trim($validos['nome']),
            'descricao' => $validos['descricao'] ?? null,
            'faixa_min' => $validos['faixa_min'] ?? null,
            'faixa_max' => $validos['faixa_max'] ?? null,
            'ativo' => $validos['ativo'] ?? $cargo->ativo ?? true,
        ])->save();

        // Só quem gere benefícios mexe nos padrões: o campo nem vem para os demais.
        if (auth()->user()->can('beneficio-gerir') && $request->has('beneficios_padrao_enviado')) {
            $salvarBeneficios->executar($cargo, $validos['beneficios_padrao'] ?? []);
        }

        return response()->json(['success' => true, 'message' => 'Cargo salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $cargo = Cargo::findOrFail($id);

        if ($cargo->colaboradores()->exists()) {
            return response()->json(['success' => false, 'message' => 'Há colaboradores neste cargo. Desative o cargo em vez de excluir.', 'data' => null], 400);
        }

        $cargo->delete();

        return response()->json(['success' => true, 'message' => 'Cargo excluído.', 'data' => null], 201);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(Cargo::query(), $request, null, ['nome'])->withCount('colaboradores');

        return DataTables::of($query)
            ->addColumn('faixa', fn (Cargo $c) => match (true) {
                $c->faixa_min !== null && $c->faixa_max !== null => Dinheiro::formatar(Dinheiro::centavos($c->faixa_min)).' a '.Dinheiro::formatar(Dinheiro::centavos($c->faixa_max)),
                $c->faixa_min !== null => 'a partir de '.Dinheiro::formatar(Dinheiro::centavos($c->faixa_min)),
                $c->faixa_max !== null => 'até '.Dinheiro::formatar(Dinheiro::centavos($c->faixa_max)),
                default => '—',
            })
            ->addColumn('colaboradores', fn (Cargo $c) => $c->colaboradores_count)
            ->addColumn('situacao', fn (Cargo $c) => view('cadastros._situacao', ['ativo' => $c->ativo]))
            ->addColumn('action', fn (Cargo $c) => view('cargos._acoes', ['cargo' => $c]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'faixa', 'colaboradores', 'situacao', 'action'])
            ->make(true);
    }
}

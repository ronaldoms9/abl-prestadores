<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Remuneracoes\AplicarReajusteLote;
use App\Actions\Remuneracoes\PreverReajusteLote;
use App\Enums\FormaReajuste;
use App\Enums\StatusColaborador;
use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\Reajuste;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Reajuste em lote por cargo ou por lista: prévia primeiro, confirmação depois (E2, fatia 6). */
class ReajusteLoteController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        return view('reajustes.lote', [
            'cargos' => Cargo::ativos()->orderBy('nome')->get(),
            'colaboradores' => Colaborador::query()->whereIn('status', StatusColaborador::abertos())->with('pessoaFisica')->get()->sortBy(fn ($c) => $c->pessoaFisica->nome)->values(),
            'ultimos' => Reajuste::query()->with('aprovador')->latest('id')->limit(10)->get(),
        ]);
    }

    public function previa(Request $request, PreverReajusteLote $prever)
    {
        [$criterio, $forma, $valor, $vigencia] = $this->parametros($request);

        try {
            $previa = $prever->executar($criterio, $forma, $valor, $vigencia);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        $formatar = fn (int $c) => Dinheiro::formatar($c);

        return response()->json(['success' => true, 'message' => '', 'data' => [
            'hash' => $previa['hash'],
            'erros' => $previa['erros'],
            'total_atual' => $formatar($previa['total_atual']),
            'total_novo' => $formatar($previa['total_novo']),
            'total_diferenca' => $formatar($previa['total_novo'] - $previa['total_atual']),
            'ignorados' => $previa['ignorados'],
            'linhas' => array_map(fn ($l) => [
                'nome' => $l['nome'],
                'cargo' => $l['cargo'],
                'atual' => $formatar($l['atual']),
                'novo' => $formatar($l['novo']),
                'diferenca' => $formatar($l['diferenca']),
                'aviso_faixa' => $l['aviso_faixa'],
                'erro' => $l['erro'],
            ], $previa['linhas']),
        ]]);
    }

    public function aplicar(Request $request, AplicarReajusteLote $aplicar)
    {
        [$criterio, $forma, $valor, $vigencia] = $this->parametros($request);
        $hash = (string) $request->input('hash');

        try {
            $reajuste = $aplicar->executar($criterio, $forma, $valor, $vigencia, $hash, auth()->user());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'Reajuste aplicado a '.count($reajuste->remuneracoes()->get()).' colaborador(es).', 'data' => []]);
    }

    /** @return array{0: array<string, mixed>, 1: FormaReajuste, 2: string, 3: Carbon} */
    private function parametros(Request $request): array
    {
        $validos = $this->validarFormulario($request, $request->all(), [
            'tipo' => ['required', Rule::in(['cargo', 'lista'])],
            'cargo_id' => ['required_if:tipo,cargo', 'nullable', Rule::exists('cargos', 'id')->whereNull('deleted_at')],
            'colaborador_ids' => ['required_if:tipo,lista', 'nullable', 'array', 'min:1', 'max:500'],
            'colaborador_ids.*' => ['integer'],
            'forma' => ['required', Rule::enum(FormaReajuste::class)],
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'vigencia' => ['required', 'date'],
        ], ['tipo' => 'critério', 'cargo_id' => 'cargo', 'colaborador_ids' => 'lista de colaboradores', 'forma' => 'forma do reajuste', 'valor' => 'valor do reajuste', 'vigencia' => 'início da vigência']);

        $criterio = $validos['tipo'] === 'cargo'
            ? ['tipo' => 'cargo', 'cargo_id' => (int) $validos['cargo_id']]
            : ['tipo' => 'lista', 'colaborador_ids' => array_map('intval', $validos['colaborador_ids'])];

        return [$criterio, FormaReajuste::from($validos['forma']), (string) $validos['valor'], Carbon::parse($validos['vigencia'])];
    }
}

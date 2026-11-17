<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Beneficios\AplicarReajusteBeneficios;
use App\Actions\Beneficios\PreverReajusteBeneficios;
use App\Enums\FormaReajuste;
use App\Http\Controllers\Controller;
use App\Models\Beneficio;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Reajuste em lote da tabela de benefícios: prévia primeiro, confirmação depois (E3, fatia 7). */
class ReajusteBeneficioController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        return view('reajustes.beneficios', [
            'beneficios' => Beneficio::ativos()->with(['valores' => fn ($q) => $q->vigentes()])->orderBy('nome')->get(),
        ]);
    }

    public function previa(Request $request, PreverReajusteBeneficios $prever)
    {
        [$ids, $forma, $valor, $vigencia] = $this->parametros($request);

        try {
            $previa = $prever->executar($ids, $forma, $valor, $vigencia);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        $formatar = fn (int $c) => Dinheiro::formatar($c);

        return response()->json(['success' => true, 'message' => '', 'data' => [
            'hash' => $previa['hash'],
            'erros' => $previa['erros'],
            'ignorados' => $previa['ignorados'],
            'linhas' => array_map(fn ($l) => [
                'nome' => $l['nome'],
                'tipo' => $l['tipo'],
                'atual' => $formatar($l['atual']),
                'novo' => $formatar($l['novo']),
                'diferenca' => $formatar($l['diferenca']),
                'erro' => $l['erro'],
            ], $previa['linhas']),
        ]]);
    }

    public function aplicar(Request $request, AplicarReajusteBeneficios $aplicar)
    {
        [$ids, $forma, $valor, $vigencia] = $this->parametros($request);

        try {
            $novas = $aplicar->executar($ids, $forma, $valor, $vigencia, (string) $request->input('hash'));
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Reajuste aplicado a '.$novas->count().' benefício(s).', 'data' => []]);
    }

    /** @return array{0: array<int, int>, 1: FormaReajuste, 2: string, 3: Carbon} */
    private function parametros(Request $request): array
    {
        $validos = $this->validarFormulario($request, $request->all(), [
            'beneficio_ids' => ['required', 'array', 'min:1', 'max:200'],
            'beneficio_ids.*' => ['integer'],
            'forma' => ['required', Rule::enum(FormaReajuste::class)],
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'vigencia' => ['required', 'date'],
        ], ['beneficio_ids' => 'benefícios', 'forma' => 'forma do reajuste', 'valor' => 'valor do reajuste', 'vigencia' => 'início da vigência']);

        return [array_map('intval', $validos['beneficio_ids']), FormaReajuste::from($validos['forma']), (string) $validos['valor'], Carbon::parse($validos['vigencia'])];
    }

    private function erro(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

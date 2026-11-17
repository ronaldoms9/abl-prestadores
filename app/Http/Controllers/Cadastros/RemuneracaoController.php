<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Remuneracoes\DefinirRemuneracaoInicial;
use App\Actions\Remuneracoes\ReajustarRemuneracao;
use App\Enums\FormaReajuste;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Remuneração do colaborador: definição inicial e reajuste individual. Nada é editado, só trocado. */
class RemuneracaoController extends Controller
{
    use ValidaFormulario;

    public function definir(Request $request, DefinirRemuneracaoInicial $definir, $id)
    {
        $colaborador = Colaborador::with('cargo')->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'vigencia' => ['required', 'date'],
        ], ['valor' => 'remuneração', 'vigencia' => 'início da vigência']);

        try {
            $linha = $definir->executar($colaborador, $validos['valor'], Carbon::parse($validos['vigencia']));
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Remuneração definida.'.$this->aviso($colaborador, $linha), 'data' => []]);
    }

    public function reajustar(Request $request, ReajustarRemuneracao $reajustar, $id)
    {
        $colaborador = Colaborador::with('cargo')->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'forma' => ['required', Rule::enum(FormaReajuste::class)],
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'vigencia' => ['required', 'date'],
        ], ['forma' => 'forma do reajuste', 'valor' => 'valor do reajuste', 'vigencia' => 'início da vigência']);

        try {
            $reajuste = $reajustar->executar($colaborador, FormaReajuste::from($validos['forma']), $validos['valor'], Carbon::parse($validos['vigencia']), auth()->user());
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        $linha = Remuneracao::where('reajuste_id', $reajuste->id)->firstOrFail();

        return response()->json(['success' => true, 'message' => 'Reajuste aplicado.'.$this->aviso($colaborador, $linha), 'data' => []]);
    }

    private function aviso(Colaborador $colaborador, Remuneracao $linha): string
    {
        $aviso = $colaborador->cargo?->avisoDeFaixa(Dinheiro::centavos($linha->valor_mensal));

        return $aviso ? ' Atenção: o novo valor está '.$aviso.'.' : '';
    }

    private function erro(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

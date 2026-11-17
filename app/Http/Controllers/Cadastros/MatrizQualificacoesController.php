<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Qualificacoes\SalvarMatrizQualificacoes;
use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\CargoQualificacao;
use App\Models\QualificacaoTipo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Matriz cargo × qualificação em grade: nenhuma, opcional ou obrigatória (E2, fatia 7). */
class MatrizQualificacoesController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        $cargos = Cargo::ativos()->orderBy('nome')->get();
        $tipos = QualificacaoTipo::ativos()->orderBy('nome')->get();

        $estados = CargoQualificacao::query()->get()->mapWithKeys(
            fn ($l) => [$l->cargo_id.':'.$l->qualificacao_tipo_id => $l->obrigatoria ? SalvarMatrizQualificacoes::OBRIGATORIA : SalvarMatrizQualificacoes::OPCIONAL]
        );

        return view('qualificacoes.matriz', ['cargos' => $cargos, 'tipos' => $tipos, 'estados' => $estados]);
    }

    public function salvar(Request $request, SalvarMatrizQualificacoes $salvar)
    {
        $validos = $this->validarFormulario($request, $request->all(), [
            'celulas' => ['required', 'array'],
            'celulas.*' => ['array'],
            'celulas.*.*' => ['required', Rule::in([SalvarMatrizQualificacoes::NENHUMA, SalvarMatrizQualificacoes::OPCIONAL, SalvarMatrizQualificacoes::OBRIGATORIA])],
        ]);

        $mudancas = $salvar->executar($validos['celulas']);

        return response()->json(['success' => true, 'message' => $mudancas === 0 ? 'Nada mudou.' : "Matriz salva ({$mudancas} alteração(ões)).", 'data' => ['mudancas' => $mudancas]]);
    }
}

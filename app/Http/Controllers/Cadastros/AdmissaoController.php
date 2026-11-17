<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\IniciarAdmissao;
use App\Actions\Admissao\MarcarItemAdmissao;
use App\Http\Controllers\Controller;
use App\Models\AdmissaoChecklist;
use App\Models\Colaborador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admissão do colaborador: iniciar o checklist, marcar itens manuais e concluir (E3, fatia 5). */
class AdmissaoController extends Controller
{
    use ValidaFormulario;

    public function iniciar(IniciarAdmissao $iniciar, $id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())->findOrFail($id);

        try {
            $iniciar->executar($colaborador);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Admissão iniciada.', 'data' => []]);
    }

    public function marcar(Request $request, MarcarItemAdmissao $marcar, $id)
    {
        $linha = AdmissaoChecklist::query()->findOrFail($id);
        Colaborador::visiveisPara(auth()->user())->findOrFail($linha->colaborador_id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'concluido' => ['required', 'boolean'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['concluido' => 'situação do item', 'observacao' => 'observação']);

        try {
            $marcar->executar($linha, (bool) $validos['concluido'], auth()->user(), $validos['observacao'] ?? null);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Item atualizado.', 'data' => []]);
    }

    public function concluir(Request $request, ConcluirAdmissao $concluir, $id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())->findOrFail($id);

        $dados = $request->all();
        $dados['remuneracao_inicial'] = ($dados['remuneracao_inicial'] ?? '') === '' ? null : $dados['remuneracao_inicial'];

        $validos = $this->validarFormulario($request, $dados, [
            'data_admissao' => ['required', 'date'],
            'remuneracao_inicial' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
        ], ['data_admissao' => 'data de admissão', 'remuneracao_inicial' => 'remuneração inicial']);

        try {
            $concluir->executar($colaborador, Carbon::parse($validos['data_admissao']), $validos['remuneracao_inicial'] ?? null);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Admissão concluída. O colaborador está ativo.', 'data' => []]);
    }

    private function erro(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

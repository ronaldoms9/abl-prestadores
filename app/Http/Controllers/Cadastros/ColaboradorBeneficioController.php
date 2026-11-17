<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Beneficios\EncerrarBeneficioColaborador;
use App\Actions\Beneficios\VincularBeneficio;
use App\Http\Controllers\Controller;
use App\Enums\StatusColaborador;
use App\Events\BeneficiosDoColaboradorAlterados;
use App\Models\Beneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Benefícios do colaborador: vincular com valor personalizado opcional e encerrar (E3, fatia 8). */
class ColaboradorBeneficioController extends Controller
{
    use ValidaFormulario;

    public function store(Request $request, VincularBeneficio $vincular, $id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())->findOrFail($id);

        $dados = $request->all();
        foreach (['valor_personalizado', 'fim'] as $campo) {
            $dados[$campo] = ($dados[$campo] ?? '') === '' ? null : $dados[$campo];
        }

        $validos = $this->validarFormulario($request, $dados, [
            'beneficio_id' => ['required', 'integer', 'exists:beneficios,id'],
            'inicio' => ['required', 'date'],
            'fim' => ['nullable', 'date', 'after:inicio'],
            'valor_personalizado' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
        ], ['beneficio_id' => 'benefício', 'inicio' => 'início', 'fim' => 'fim', 'valor_personalizado' => 'valor personalizado']);

        try {
            $beneficio = Beneficio::findOrFail($validos['beneficio_id']);
            $vincular->executar(
                $colaborador,
                $beneficio,
                Carbon::parse($validos['inicio']),
                $validos['valor_personalizado'] ?? null,
                isset($validos['fim']) ? Carbon::parse($validos['fim']) : null,
            );
            $this->avisarAditivo($colaborador, 'Benefício incluído: '.$beneficio->nome, $validos['inicio']);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Benefício vinculado.', 'data' => []]);
    }

    public function encerrar(Request $request, EncerrarBeneficioColaborador $encerrar, $id)
    {
        $vinculo = ColaboradorBeneficio::with(['beneficio', 'colaborador'])->findOrFail($id);
        Colaborador::visiveisPara(auth()->user())->findOrFail($vinculo->colaborador_id);

        $validos = $this->validarFormulario($request, $request->all(), ['fim' => ['nullable', 'date']], ['fim' => 'data de fim']);

        try {
            $fim = isset($validos['fim']) ? Carbon::parse($validos['fim']) : now();
            $encerrar->executar($vinculo, $fim);
            $this->avisarAditivo($vinculo->colaborador, 'Benefício encerrado: '.$vinculo->beneficio->nome, $fim->toDateString());
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Benefício encerrado.', 'data' => []]);
    }

    /** Quem já foi admitido e muda de benefício ganha um rascunho de aditivo (E3, fatia 9). */
    private function avisarAditivo(Colaborador $colaborador, string $descricao, string $inicio): void
    {
        if ($colaborador->status === StatusColaborador::Ativo) {
            BeneficiosDoColaboradorAlterados::dispatch($colaborador, $descricao, $inicio);
        }
    }

    private function erro(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

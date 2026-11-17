<?php

namespace App\Http\Controllers\Cadastros;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Valida o formulário em modal e devolve, no formato que o axios-form lê
 * ({success, message}), todas as mensagens juntas.
 */
trait ValidaFormulario
{
    /**
     * @param  array<string, mixed>  $regras
     * @param  array<string, string>  $atributos
     * @return array<string, mixed>
     */
    protected function validarFormulario(Request $request, array $dados, array $regras, array $atributos = []): array
    {
        $validador = Validator::make($dados, $regras, [], $atributos);

        if ($validador->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => implode(' ', $validador->errors()->all()),
                'data' => null,
            ], 422));
        }

        return $validador->validated();
    }
}

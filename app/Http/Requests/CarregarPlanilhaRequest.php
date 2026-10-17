<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Upload da planilha de pessoas (fatia 5b). O conteúdo é validado em CarregarPlanilha. */
class CarregarPlanilhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // CSV costuma chegar como text/plain: vale a extensão, e o leitor confere o conteúdo.
            'arquivo' => ['required', 'file', 'extensions:csv,xlsx', 'mimes:csv,txt,xlsx', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['arquivo' => 'planilha'];
    }

    public function messages(): array
    {
        return [
            'arquivo.extensions' => 'Envie um arquivo .xlsx ou .csv.',
            'arquivo.mimes' => 'Envie um arquivo .xlsx ou .csv.',
            'arquivo.max' => 'A planilha pode ter até 2 MB.',
        ];
    }
}

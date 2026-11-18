<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Calendario\ImportarFeriadosNacionais;
use App\Actions\Calendario\SalvarFeriado;
use App\Enums\AbrangenciaFeriado;
use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Feriados nacionais, estaduais e municipais (por obra), usados no cálculo do fechamento (E4, fatia 5). */
class FeriadoController extends Controller
{
    use ValidaFormulario;

    public function index(Request $request)
    {
        $ano = (int) ($request->validate(['ano' => ['nullable', 'integer', 'between:2000,2100']])['ano'] ?? now()->year);

        return view('feriados.index', [
            'ano' => $ano,
            'feriados' => Feriado::query()->with('obra')->whereYear('data', $ano)->orderBy('data')->orderBy('abrangencia')->get(),
        ]);
    }

    public function form($id = 0)
    {
        return view('feriados.form', [
            'feriado' => $id ? Feriado::findOrFail($id) : new Feriado(['abrangencia' => AbrangenciaFeriado::Nacional]),
            'abrangencias' => AbrangenciaFeriado::cases(),
            'obras' => Obra::query()->ativos()->orderBy('descricao')->get(['id', 'codigo', 'descricao']),
        ]);
    }

    public function store(Request $request, SalvarFeriado $salvar, $id = 0)
    {
        $feriado = $id ? Feriado::findOrFail($id) : null;

        $validos = $this->validarFormulario($request, $request->all(), [
            'data' => ['required', 'date'],
            'descricao' => ['required', 'string', 'max:255'],
            'abrangencia' => ['required', Rule::enum(AbrangenciaFeriado::class)],
            'obra_id' => ['nullable', Rule::exists('obras', 'id')],
        ], ['data' => 'data', 'descricao' => 'descrição', 'obra_id' => 'obra']);

        try {
            $salvar->executar($validos, $feriado);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Feriado salvo.', 'data' => []]);
    }

    public function delete(SalvarFeriado $salvar, $id)
    {
        try {
            $salvar->excluir(Feriado::findOrFail($id));
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Feriado excluído.', 'data' => null], 201);
    }

    public function importar(Request $request, ImportarFeriadosNacionais $importar)
    {
        $ano = (int) $request->validate(['ano' => ['required', 'integer', 'between:2000,2100']])['ano'];

        try {
            $r = $importar->executar($ano);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        $mensagem = "{$r['criados']} feriado(s) nacional(is) de {$ano} importado(s); {$r['existentes']} já existia(m).";
        if ($r['em_competencia_fechada'] > 0) {
            $mensagem .= " {$r['em_competencia_fechada']} ficaram de fora por cair em competência fechada.";
        }

        return response()->json(['success' => true, 'message' => $mensagem, 'data' => $r]);
    }

    private function recusa(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

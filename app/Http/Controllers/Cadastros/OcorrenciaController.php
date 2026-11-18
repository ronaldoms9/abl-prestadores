<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Ocorrencias\SalvarOcorrencia;
use App\Enums\StatusColaborador;
use App\Enums\TipoOcorrencia;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Ocorrencia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Faltas e afastamentos (E4, fatia 5). */
class OcorrenciaController extends Controller
{
    use ValidaFormulario;

    public function index(Request $request)
    {
        $dados = $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $mes = Carbon::createFromFormat('Y-m-d', ($dados['mes'] ?? now()->format('Y-m')).'-01')->startOfDay();

        return view('ocorrencias.index', [
            'mes' => $mes,
            'ocorrencias' => Ocorrencia::query()
                ->with('colaborador.pessoaFisica')
                ->whereDate('inicio', '<=', $mes->copy()->endOfMonth())
                ->whereDate('fim', '>=', $mes)
                ->orderBy('inicio')->get(),
        ]);
    }

    public function form($id = 0)
    {
        $ocorrencia = $id ? Ocorrencia::with('colaborador.pessoaFisica')->findOrFail($id) : new Ocorrencia(['tipo' => TipoOcorrencia::Falta, 'inicio' => today(), 'fim' => today()]);

        return view('ocorrencias.form', [
            'ocorrencia' => $ocorrencia,
            'tipos' => TipoOcorrencia::cases(),
            'colaboradores' => Colaborador::query()->with('pessoaFisica')
                ->where('status', StatusColaborador::Ativo)->get()
                ->sortBy(fn ($c) => $c->pessoaFisica->nome)->values(),
        ]);
    }

    public function store(Request $request, SalvarOcorrencia $salvar, $id = 0)
    {
        $ocorrencia = $id ? Ocorrencia::findOrFail($id) : null;

        $validos = $this->validarFormulario($request, $request->all(), [
            'colaborador_id' => [$ocorrencia ? 'nullable' : 'required', Rule::exists('colaboradores', 'id')],
            'tipo' => ['required', Rule::enum(TipoOcorrencia::class)],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date'],
            'abona' => ['nullable', 'boolean'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['colaborador_id' => 'colaborador', 'inicio' => 'início', 'observacao' => 'observação']);

        try {
            $salvar->executar($validos, $ocorrencia);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Ocorrência salva.', 'data' => []]);
    }

    public function delete(SalvarOcorrencia $salvar, $id)
    {
        try {
            $salvar->excluir(Ocorrencia::findOrFail($id));
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Ocorrência excluída.', 'data' => null], 201);
    }

    private function recusa(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

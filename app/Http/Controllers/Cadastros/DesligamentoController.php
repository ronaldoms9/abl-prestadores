<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Desligamento\DesligarColaborador;
use App\Actions\Desligamento\PreviaDesligamento;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Desligamento: formulário com prévia dos valores finais e registro (E3, fatia 6). */
class DesligamentoController extends Controller
{
    use ValidaFormulario;

    public function form($id)
    {
        $colaborador = $this->colaborador($id);
        abort_if($colaborador->status === StatusColaborador::Desligado, 404);

        $temContrato = Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->exists();

        return view('colaboradores.desligar', [
            'colaborador' => $colaborador,
            'temContrato' => $temContrato,
            'modelosDistrato' => ContratoModelo::ativos()->where('tipo', TipoContrato::Distrato->value)->orderBy('nome')->get(),
            'aviso' => PreviaDesligamento::AVISO,
        ]);
    }

    public function previa(Request $request, PreviaDesligamento $previa, $id)
    {
        $colaborador = $this->colaborador($id);
        $validos = $this->validarFormulario($request, $request->all(), ['data' => ['required', 'date']], ['data' => 'data do desligamento']);

        $r = $previa->executar($colaborador, Carbon::parse($validos['data']));
        $moeda = fn (int $c) => Dinheiro::formatar($c);

        return response()->json(['success' => true, 'message' => '', 'data' => [
            'periodo' => $r['inicio'] ? $r['inicio']->format('d/m/Y').' a '.$r['fim']->format('d/m/Y').' ('.$r['dias'].' dias)' : null,
            'remuneracao' => $moeda($r['remuneracao']),
            'beneficios' => array_map(fn ($b) => ['nome' => $b['nome'], 'valor' => $moeda($b['valor']), 'valor_mensal' => $moeda($b['valor_mensal']), 'personalizado' => $b['personalizado']], $r['beneficios']),
            'total_beneficios' => $moeda($r['total_beneficios']),
            'total' => $moeda($r['total']),
            'avisos' => $r['avisos'],
        ]]);
    }

    public function store(Request $request, DesligarColaborador $desligar, $id)
    {
        $colaborador = $this->colaborador($id);

        $dados = $request->all();
        $dados['modelo_distrato_id'] = ($dados['modelo_distrato_id'] ?? '') === '' ? null : $dados['modelo_distrato_id'];

        $validos = $this->validarFormulario($request, $dados, [
            'data' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'gerar_distrato' => ['nullable', 'boolean'],
            'modelo_distrato_id' => ['nullable', Rule::exists('contrato_modelos', 'id')->whereNull('deleted_at')],
        ], ['data' => 'data do desligamento', 'motivo' => 'motivo', 'observacao' => 'observação', 'modelo_distrato_id' => 'modelo de distrato']);

        try {
            $resultado = $desligar->executar(
                $colaborador,
                Carbon::parse($validos['data']),
                $validos['motivo'],
                $validos['observacao'] ?? null,
                (bool) ($validos['gerar_distrato'] ?? false),
                isset($validos['modelo_distrato_id']) ? ContratoModelo::find($validos['modelo_distrato_id']) : null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        $mensagem = 'Colaborador desligado.';
        if ($resultado['distrato']) {
            $mensagem .= ' Distrato '.$resultado['distrato']->numero.' gerado.';
        }
        if ($resultado['avisos'] !== []) {
            $mensagem .= ' '.implode(' ', $resultado['avisos']);
        }

        return response()->json(['success' => true, 'message' => $mensagem, 'data' => []]);
    }

    private function colaborador($id): Colaborador
    {
        return Colaborador::visiveisPara(auth()->user())->with('pessoaFisica')->findOrFail($id);
    }
}

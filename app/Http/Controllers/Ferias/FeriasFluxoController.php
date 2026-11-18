<?php

namespace App\Http\Controllers\Ferias;

use App\Enums\EscopoFluxoFerias;
use App\Enums\StatusColaborador;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\Equipe;
use App\Models\FeriasFluxo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cadastro dos fluxos de aprovação de férias por colaborador, equipe e função (E4, fatia 9). */
class FeriasFluxoController extends Controller
{
    use ValidaFormulario;

    public function form($id = 0)
    {
        return view('ferias.fluxo-form', [
            'fluxo' => $id ? FeriasFluxo::findOrFail($id) : new FeriasFluxo(['escopo' => EscopoFluxoFerias::Equipe, 'ativo' => true]),
            'escopos' => array_filter(EscopoFluxoFerias::cases(), fn ($e) => $e !== EscopoFluxoFerias::Padrao),
            'equipes' => Equipe::query()->orderBy('nome')->get(['id', 'nome']),
            'cargos' => Cargo::query()->orderBy('nome')->get(['id', 'nome']),
            'colaboradores' => Colaborador::query()->with('pessoaFisica:id,nome')->where('status', StatusColaborador::Ativo->value)->get(['id', 'pessoa_fisica_id'])->sortBy(fn ($c) => $c->pessoaFisica?->nome)->values(),
        ]);
    }

    public function store(Request $request, $id = 0)
    {
        $fluxo = $id ? FeriasFluxo::findOrFail($id) : new FeriasFluxo;

        if ($fluxo->exists && $fluxo->ePadrao()) {
            $validos = $this->validarFormulario($request, $request->all(), ['nome' => ['required', 'string', 'max:120']], ['nome' => 'nome']);
            $fluxo->update(['nome' => $validos['nome']]);

            return response()->json(['success' => true, 'message' => 'Fluxo salvo.', 'data' => []]);
        }

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:120'],
            'escopo' => ['required', Rule::enum(EscopoFluxoFerias::class)->except([EscopoFluxoFerias::Padrao])],
            'equipe_id' => ['nullable', 'required_if:escopo,equipe,equipe_cargo', Rule::exists('equipes', 'id')],
            'cargo_id' => ['nullable', 'required_if:escopo,cargo,equipe_cargo', Rule::exists('cargos', 'id')],
            'colaborador_id' => ['nullable', 'required_if:escopo,colaborador', Rule::exists('colaboradores', 'id')],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome', 'escopo' => 'escopo', 'equipe_id' => 'equipe', 'cargo_id' => 'função', 'colaborador_id' => 'colaborador']);

        $escopo = EscopoFluxoFerias::from($validos['escopo']);
        $alvo = [
            'equipe_id' => in_array($escopo, [EscopoFluxoFerias::Equipe, EscopoFluxoFerias::EquipeCargo], true) ? (int) $validos['equipe_id'] : null,
            'cargo_id' => in_array($escopo, [EscopoFluxoFerias::Cargo, EscopoFluxoFerias::EquipeCargo], true) ? (int) $validos['cargo_id'] : null,
            'colaborador_id' => $escopo === EscopoFluxoFerias::Colaborador ? (int) $validos['colaborador_id'] : null,
        ];

        $repetido = FeriasFluxo::query()->where('escopo', $escopo->value)->whereKeyNot($fluxo->id ?? 0)
            ->where('equipe_id', $alvo['equipe_id'])->where('cargo_id', $alvo['cargo_id'])->where('colaborador_id', $alvo['colaborador_id'])->exists();

        if ($repetido) {
            return response()->json(['success' => false, 'message' => 'Já existe um fluxo para esse mesmo alvo; edite o existente.', 'data' => null], 422);
        }

        $fluxo->fill(['nome' => $validos['nome'], 'escopo' => $escopo, 'ativo' => (bool) ($validos['ativo'] ?? true)] + $alvo)->save();

        return response()->json(['success' => true, 'message' => 'Fluxo salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $fluxo = FeriasFluxo::findOrFail($id);

        if ($fluxo->ePadrao()) {
            return response()->json(['success' => false, 'message' => 'O fluxo padrão não pode ser excluído.', 'data' => null], 422);
        }

        $fluxo->niveis()->delete();
        $fluxo->delete();

        return response()->json(['success' => true, 'message' => 'Fluxo excluído. As solicitações pendentes dele seguem no fluxo em que foram feitas.', 'data' => null], 201);
    }
}

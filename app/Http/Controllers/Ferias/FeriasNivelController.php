<?php

namespace App\Http\Controllers\Ferias;

use App\Enums\TipoAprovadorFerias;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\FeriasFluxo;
use App\Models\FeriasNivel;
use App\Models\User;
use App\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Fluxos de aprovação de férias e seus níveis: ordem, tipo de aprovador, permissão ou usuário (E4, fatias 3 e 9). */
class FeriasNivelController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        return view('ferias.niveis', [
            'fluxos' => FeriasFluxo::query()->with(['niveis.permissao', 'niveis.usuario', 'equipe', 'cargo', 'colaborador.pessoaFisica'])->get()
                ->sortBy([fn ($a, $b) => $b->escopo->especificidade() <=> $a->escopo->especificidade(), fn ($a, $b) => $a->nome <=> $b->nome])->values(),
        ]);
    }

    public function form(Request $request, $id = 0)
    {
        $fluxoId = $id ? FeriasNivel::findOrFail($id)->fluxo_id : (int) ($request->query('fluxo') ?: FeriasFluxo::padrao()->id);

        return view('ferias.nivel-form', [
            'fluxo' => FeriasFluxo::findOrFail($fluxoId),
            'usuarios' => User::query()->ativos()->orderBy('name')->get(['id', 'name']),
            'nivel' => $id ? FeriasNivel::findOrFail($id) : new FeriasNivel(['fluxo_id' => $fluxoId, 'tipo_aprovador' => TipoAprovadorFerias::Permissao, 'ativo' => true, 'ordem' => (FeriasNivel::where('fluxo_id', $fluxoId)->max('ordem') ?? 0) + 1]),
            'tipos' => TipoAprovadorFerias::cases(),
            'permissoes' => Permission::query()->orderBy('display_name')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function store(Request $request, $id = 0)
    {
        $nivel = $id ? FeriasNivel::query()->with('fluxo')->findOrFail($id) : new FeriasNivel;
        $fluxoId = $nivel->exists ? $nivel->fluxo_id : (int) ($request->input('fluxo_id') ?: FeriasFluxo::padrao()->id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'fluxo_id' => ['nullable', Rule::exists('ferias_fluxos', 'id')->whereNull('deleted_at')],
            'ordem' => ['required', 'integer', 'between:1,20', Rule::unique('ferias_niveis', 'ordem')->ignore($nivel->id)->where('fluxo_id', $fluxoId)->whereNull('deleted_at')],
            'tipo_aprovador' => ['required', Rule::enum(TipoAprovadorFerias::class)],
            'permission_id' => ['nullable', 'required_if:tipo_aprovador,permissao', Rule::exists('permissions', 'id')],
            'user_id' => ['nullable', 'required_if:tipo_aprovador,usuario', Rule::exists('users', 'id')],
            'ativo' => ['nullable', 'boolean'],
        ], ['ordem' => 'ordem', 'tipo_aprovador' => 'tipo de aprovador', 'permission_id' => 'permissão', 'user_id' => 'usuário']);

        $ativo = (bool) ($validos['ativo'] ?? true);

        if (! $ativo && $nivel->exists && $this->eOUnicoAtivoDoPadrao($nivel)) {
            return $this->recusa('Mantenha ao menos um nível ativo no fluxo padrão; sem nível, toda solicitação vai direto a quem tem a permissão de aprovar qualquer férias.');
        }

        $tipo = $validos['tipo_aprovador'];
        $nivel->fill([
            'fluxo_id' => $fluxoId,
            'ordem' => $validos['ordem'],
            'tipo_aprovador' => $tipo,
            'permission_id' => $tipo === TipoAprovadorFerias::Permissao->value ? $validos['permission_id'] : null,
            'user_id' => $tipo === TipoAprovadorFerias::Usuario->value ? $validos['user_id'] : null,
            'ativo' => $ativo,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Nível salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $nivel = FeriasNivel::query()->with('fluxo')->findOrFail($id);

        if ($this->eOUnicoAtivoDoPadrao($nivel)) {
            return $this->recusa('Mantenha ao menos um nível ativo no fluxo padrão.');
        }

        $nivel->delete();

        return response()->json(['success' => true, 'message' => 'Nível excluído.', 'data' => null], 201);
    }

    /** O fluxo padrão sempre precisa de um nível ativo: é o que atende quem não tem fluxo próprio. */
    private function eOUnicoAtivoDoPadrao(FeriasNivel $nivel): bool
    {
        return $nivel->ativo
            && $nivel->fluxo?->ePadrao()
            && ! FeriasNivel::query()->where('fluxo_id', $nivel->fluxo_id)->where('ativo', true)->whereKeyNot($nivel->id)->exists();
    }

    private function recusa(string $mensagem)
    {
        return response()->json(['success' => false, 'message' => $mensagem, 'data' => null], 422);
    }
}

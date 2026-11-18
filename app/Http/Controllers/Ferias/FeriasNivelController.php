<?php

namespace App\Http\Controllers\Ferias;

use App\Enums\TipoAprovadorFerias;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\FeriasNivel;
use App\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Níveis do fluxo de aprovação de férias: ordem, tipo de aprovador e permissão (E4, fatia 3). */
class FeriasNivelController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        return view('ferias.niveis', [
            'niveis' => FeriasNivel::query()->with('permissao')->orderBy('ordem')->get(),
        ]);
    }

    public function form($id = 0)
    {
        return view('ferias.nivel-form', [
            'nivel' => $id ? FeriasNivel::findOrFail($id) : new FeriasNivel(['tipo_aprovador' => TipoAprovadorFerias::Permissao, 'ativo' => true, 'ordem' => (FeriasNivel::max('ordem') ?? 0) + 1]),
            'tipos' => TipoAprovadorFerias::cases(),
            'permissoes' => Permission::query()->orderBy('display_name')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function store(Request $request, $id = 0)
    {
        $nivel = $id ? FeriasNivel::findOrFail($id) : new FeriasNivel;

        $validos = $this->validarFormulario($request, $request->all(), [
            'ordem' => ['required', 'integer', 'between:1,20', Rule::unique('ferias_niveis', 'ordem')->ignore($nivel->id)->whereNull('deleted_at')],
            'tipo_aprovador' => ['required', Rule::enum(TipoAprovadorFerias::class)],
            'permission_id' => ['nullable', 'required_if:tipo_aprovador,permissao', Rule::exists('permissions', 'id')],
            'ativo' => ['nullable', 'boolean'],
        ], ['ordem' => 'ordem', 'tipo_aprovador' => 'tipo de aprovador', 'permission_id' => 'permissão']);

        $ativo = (bool) ($validos['ativo'] ?? true);

        if (! $ativo && $nivel->exists && $this->eOUnicoAtivo($nivel)) {
            return $this->recusa('Mantenha ao menos um nível ativo; sem nível, toda solicitação vai direto a quem tem a permissão de aprovar qualquer férias.');
        }

        $nivel->fill([
            'ordem' => $validos['ordem'],
            'tipo_aprovador' => $validos['tipo_aprovador'],
            'permission_id' => $validos['tipo_aprovador'] === TipoAprovadorFerias::Permissao->value ? $validos['permission_id'] : null,
            'ativo' => $ativo,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Nível salvo.', 'data' => []]);
    }

    public function delete($id)
    {
        $nivel = FeriasNivel::findOrFail($id);

        if ($this->eOUnicoAtivo($nivel)) {
            return $this->recusa('Mantenha ao menos um nível ativo.');
        }

        $nivel->delete();

        return response()->json(['success' => true, 'message' => 'Nível excluído.', 'data' => null], 201);
    }

    private function eOUnicoAtivo(FeriasNivel $nivel): bool
    {
        return $nivel->ativo && ! FeriasNivel::query()->where('ativo', true)->whereKeyNot($nivel->id)->exists();
    }

    private function recusa(string $mensagem)
    {
        return response()->json(['success' => false, 'message' => $mensagem, 'data' => null], 422);
    }
}

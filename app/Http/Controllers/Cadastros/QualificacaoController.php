<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Qualificacoes\RegistrarQualificacao;
use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorQualificacao;
use App\Models\QualificacaoTipo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Qualificações dos colaboradores (E2, fatia 7): lista de pendências, registro
 * com certificado, download e exclusão. Colaborador e certificado só para quem
 * vê aquele colaborador (equipes que o gestor gere, D7).
 */
class QualificacaoController extends Controller
{
    use ValidaFormulario;

    public function pendentes(Request $request, SituacaoQualificacoes $qualificacoes)
    {
        $cargoId = $request->integer('cargo_id') ?: null;
        $dias = (int) config('cadastros.alertas.qualificacoes_dias');

        return view('qualificacoes.pendentes', [
            'situacoes' => $qualificacoes->analisar(now(), $dias, Colaborador::visiveisPara(auth()->user()), $cargoId),
            'cargos' => Cargo::ativos()->orderBy('nome')->get(),
            'cargoId' => $cargoId,
            'dias' => $dias,
        ]);
    }

    public function registrar(Request $request, RegistrarQualificacao $registrar, $id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all() + ['certificado' => $request->file('certificado')], [
            'qualificacao_tipo_id' => ['required', Rule::exists('qualificacao_tipos', 'id')->whereNull('deleted_at')],
            'data_conclusao' => ['required', 'date'],
            'instituicao' => ['nullable', 'string', 'max:255'],
            'certificado' => ['nullable', 'file', 'max:'.config('cadastros.documentos.tamanho_maximo_kb'), 'mimes:'.implode(',', config('cadastros.documentos.extensoes'))],
        ], ['qualificacao_tipo_id' => 'qualificação', 'data_conclusao' => 'data de conclusão', 'instituicao' => 'instituição', 'certificado' => 'certificado']);

        try {
            $registrar->executar(
                $colaborador,
                QualificacaoTipo::findOrFail($validos['qualificacao_tipo_id']),
                Carbon::parse($validos['data_conclusao']),
                $validos['instituicao'] ?? null,
                $request->file('certificado'),
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'Qualificação registrada.', 'data' => []]);
    }

    public function certificado($id)
    {
        $qualificacao = ColaboradorQualificacao::findOrFail($id);
        $this->autorizar($qualificacao);

        $disco = Storage::disk(config('cadastros.documentos.disco'));
        abort_unless($qualificacao->certificado_path !== null && $disco->exists($qualificacao->certificado_path), 404, 'Certificado não encontrado.');

        return $disco->download($qualificacao->certificado_path, $qualificacao->certificado_nome ?? basename($qualificacao->certificado_path));
    }

    public function excluir(RegistrarQualificacao $registrar, $id)
    {
        $qualificacao = ColaboradorQualificacao::with(['colaborador', 'tipo'])->findOrFail($id);
        $this->autorizar($qualificacao);

        $registrar->excluir($qualificacao);

        return response()->json(['success' => true, 'message' => 'Qualificação excluída.', 'data' => null], 201);
    }

    private function autorizar(ColaboradorQualificacao $qualificacao): void
    {
        abort_unless(Colaborador::visiveisPara(auth()->user())->whereKey($qualificacao->colaborador_id)->exists(), 404);
    }
}

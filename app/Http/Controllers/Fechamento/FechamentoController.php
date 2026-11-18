<?php

namespace App\Http\Controllers\Fechamento;

use App\Actions\Fechamento\AbrirCompetencia;
use App\Actions\Fechamento\AprovarCompetencia;
use App\Actions\Fechamento\CalcularFechamentoColaborador;
use App\Actions\Fechamento\LancarAjusteAvulso;
use App\Actions\Fechamento\ReabrirCompetencia;
use App\Enums\TipoAjuste;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Jobs\CalcularCompetencia;
use App\Models\AjusteAvulso;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Competências, conferência, ajustes e aprovação do fechamento mensal (E4, fatia 7). */
class FechamentoController extends Controller
{
    use ValidaFormulario;

    public function index()
    {
        return view('fechamento.index', [
            'competencias' => Competencia::query()
                ->withCount('fechamentos')
                ->withSum('fechamentos', 'total_liquido')
                ->orderByDesc('ano_mes')->get(),
            'mesSugerido' => now()->format('Y-m'),
        ]);
    }

    public function abrir(Request $request, AbrirCompetencia $abrir)
    {
        $validos = $this->validarFormulario($request, $request->all(), ['ano_mes' => ['required', 'string', 'size:7']], ['ano_mes' => 'mês']);

        try {
            $competencia = $abrir->executar($validos['ano_mes']);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Competência aberta. O cálculo roda em segundo plano; atualize a tela em instantes.', 'data' => ['id' => $competencia->id, 'url' => route('fechamento.show', $competencia->id)]]);
    }

    public function show($id)
    {
        $competencia = Competencia::query()->with('aprovador')->findOrFail($id);
        $fechamentos = Fechamento::query()
            ->with('colaborador.pessoaFisica')
            ->where('competencia_id', $competencia->id)
            ->get()->sortBy(fn (Fechamento $f) => $f->colaborador->pessoaFisica->nome)->values();

        return view('fechamento.show', [
            'competencia' => $competencia,
            'fechamentos' => $fechamentos,
            'totais' => [
                'remuneracao' => (float) $fechamentos->sum('valor_remuneracao'),
                'beneficios' => (float) $fechamentos->sum('total_beneficios'),
                'ajustes' => (float) $fechamentos->sum('total_ajustes'),
                'liquido' => (float) $fechamentos->sum('total_liquido'),
            ],
            'comAviso' => $fechamentos->filter(fn (Fechamento $f) => ! empty($f->snapshot['avisos'] ?? []))->count(),
        ]);
    }

    public function detalhe($id)
    {
        $fechamento = Fechamento::query()->with(['competencia', 'colaborador.pessoaFisica', 'itens'])->findOrFail($id);
        $ajustes = AjusteAvulso::query()->where('competencia_id', $fechamento->competencia_id)->where('colaborador_id', $fechamento->colaborador_id)->orderBy('id')->get();

        return view('fechamento.detalhe', ['fechamento' => $fechamento, 'ajustes' => $ajustes, 'tipos' => TipoAjuste::cases()]);
    }

    public function recalcular(CalcularFechamentoColaborador $calcular, $id)
    {
        $fechamento = Fechamento::query()->with(['competencia', 'colaborador'])->findOrFail($id);

        try {
            $calcular->executar($fechamento->colaborador, $fechamento->competencia);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        Auditoria::registrar('fechamento-recalculado', $fechamento->competencia, ['colaborador_id' => $fechamento->colaborador_id]);

        return response()->json(['success' => true, 'message' => 'Fechamento recalculado.', 'data' => []]);
    }

    public function recalcularTodos($id)
    {
        $competencia = Competencia::findOrFail($id);

        if ($competencia->status->imutavel()) {
            return $this->recusa(ValidationException::withMessages(['competencia' => 'A competência já foi aprovada; os valores não mudam mais.']));
        }

        CalcularCompetencia::dispatch($competencia->id);
        Auditoria::registrar('fechamento-recalculado', $competencia, ['todos' => true]);

        return response()->json(['success' => true, 'message' => 'Recálculo de todos os colaboradores iniciado; atualize a tela em instantes.', 'data' => []]);
    }

    public function ajusteStore(Request $request, LancarAjusteAvulso $lancar, $id)
    {
        $fechamento = Fechamento::query()->with(['competencia', 'colaborador'])->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'tipo' => ['required', Rule::enum(TipoAjuste::class)],
            'valor' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'justificativa' => ['required', 'string', 'max:500'],
        ], ['valor' => 'valor', 'justificativa' => 'justificativa']);

        try {
            $lancar->executar($fechamento->competencia, $fechamento->colaborador, TipoAjuste::from($validos['tipo']), $validos['valor'], $validos['justificativa'], $request->user()->id);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Ajuste lançado e fechamento recalculado.', 'data' => []]);
    }

    public function ajusteDelete(LancarAjusteAvulso $lancar, $id)
    {
        try {
            $lancar->remover(AjusteAvulso::findOrFail($id));
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Ajuste removido e fechamento recalculado.', 'data' => null], 201);
    }

    public function aprovar(Request $request, AprovarCompetencia $aprovar, $id)
    {
        try {
            $aprovar->executar(Competencia::findOrFail($id), $request->user(), $request->boolean('ignorar_avisos'));
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Competência aprovada. Os valores do mês não mudam mais.', 'data' => []]);
    }

    public function reabrir(Request $request, ReabrirCompetencia $reabrir, $id)
    {
        $validos = $this->validarFormulario($request, $request->all(), ['justificativa' => ['required', 'string', 'max:500']], ['justificativa' => 'justificativa']);

        try {
            $reabrir->executar(Competencia::findOrFail($id), $validos['justificativa']);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Competência reaberta para conferência.', 'data' => []]);
    }

    private function recusa(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

<?php

namespace App\Http\Controllers\Ferias;

use App\Actions\Ferias\CancelarFerias;
use App\Actions\Ferias\DecidirFerias;
use App\Actions\Ferias\SolicitarFerias;
use App\Enums\DecisaoFerias;
use App\Enums\StatusSolicitacaoFerias;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Support\Ferias\AprovadoresFerias;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Solicitações de férias e a fila de aprovação (E4, fatia 3). */
class FeriasController extends Controller
{
    use ValidaFormulario;

    private const LIMITE = 200;

    public function index(Request $request, AprovadoresFerias $aprovadores)
    {
        $usuario = $request->user();
        $visao = $request->query('visao') === 'todas' && $usuario->can('ferias-list') ? 'todas' : 'decidir';
        $status = StatusSolicitacaoFerias::tryFrom((string) $request->query('status'));

        $query = FeriasSolicitacao::query()
            ->with(['colaborador.pessoaFisica', 'periodo', 'solicitante', 'aprovacoes.aprovador'])
            ->whereHas('colaborador', fn ($q) => $q->visiveisPara($usuario));

        if ($visao === 'decidir') {
            $solicitacoes = $query->where('status', StatusSolicitacaoFerias::Pendente->value)->orderBy('inicio')->limit(self::LIMITE)->get();
            $lote = $aprovadores->carregarLote($solicitacoes, now());
            $solicitacoes = $solicitacoes->filter(fn (FeriasSolicitacao $s) => $aprovadores->podeDecidir($s, $usuario, now(), $lote))->values();
        } else {
            $solicitacoes = $query->when($status, fn ($q) => $q->where('status', $status->value))->orderByDesc('inicio')->limit(self::LIMITE)->get();
            $lote = $aprovadores->carregarLote($solicitacoes->where('status', StatusSolicitacaoFerias::Pendente), now());
        }

        $podeDecidir = $visao === 'decidir'
            ? $solicitacoes->pluck('id')->all()
            : $solicitacoes->filter(fn (FeriasSolicitacao $s) => $aprovadores->podeDecidir($s, $usuario, now(), $lote))->pluck('id')->all();

        return view('ferias.index', [
            'visao' => $visao,
            'status' => $status,
            'statuses' => StatusSolicitacaoFerias::cases(),
            'solicitacoes' => $solicitacoes,
            'podeDecidir' => $podeDecidir,
            'podeCancelar' => $usuario->can('ferias-solicitar'),
            'limite' => self::LIMITE,
        ]);
    }

    public function solicitar(Request $request, SolicitarFerias $solicitar, $id)
    {
        $colaborador = Colaborador::visiveisPara($request->user())->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'periodo_id' => ['required', Rule::exists('ferias_periodos', 'id')],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['periodo_id' => 'período aquisitivo', 'inicio' => 'início', 'observacao' => 'observação']);

        try {
            $resultado = $solicitar->executar(
                $colaborador,
                FeriasPeriodo::findOrFail($validos['periodo_id']),
                Carbon::parse($validos['inicio']),
                Carbon::parse($validos['fim']),
                $request->user(),
                $validos['observacao'] ?? null,
            );
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        $mensagem = 'Solicitação registrada e enviada para aprovação.';
        if ($resultado['avisos'] !== []) {
            $mensagem .= ' Atenção: '.implode(' ', $resultado['avisos']);
        }

        return response()->json(['success' => true, 'message' => $mensagem, 'data' => ['id' => $resultado['solicitacao']->id, 'avisos' => $resultado['avisos']]]);
    }

    public function decidirForm(Request $request, AprovadoresFerias $aprovadores, $id)
    {
        $solicitacao = $this->solicitacao($request, $id, ['colaborador.pessoaFisica', 'aprovacoes.aprovador']);
        abort_unless($aprovadores->podeDecidir($solicitacao, $request->user(), now()), 403);

        return view('ferias.decidir', ['solicitacao' => $solicitacao]);
    }

    public function decidir(Request $request, DecidirFerias $decidir, $id)
    {
        $solicitacao = $this->solicitacao($request, $id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'decisao' => ['required', Rule::in([DecisaoFerias::Aprovada->value, DecisaoFerias::Recusada->value])],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['decisao' => 'decisão', 'observacao' => 'observação']);

        try {
            $decidir->executar($solicitacao, $request->user(), DecisaoFerias::from($validos['decisao']), $validos['observacao'] ?? null);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => $validos['decisao'] === 'aprovada' ? 'Decisão registrada.' : 'Solicitação recusada.', 'data' => []]);
    }

    public function cancelar(Request $request, CancelarFerias $cancelar, $id)
    {
        try {
            $cancelar->executar($this->solicitacao($request, $id));
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Férias canceladas.', 'data' => []]);
    }

    /** @param  list<string>  $com */
    private function solicitacao(Request $request, $id, array $com = []): FeriasSolicitacao
    {
        return FeriasSolicitacao::query()->with($com)
            ->whereHas('colaborador', fn ($q) => $q->visiveisPara($request->user()))
            ->findOrFail($id);
    }

    private function recusa(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

<?php

namespace App\Http\Controllers\Integracoes;

use App\Actions\Integracoes\NaoReprocessavel;
use App\Actions\Integracoes\ReprocessarFalha;
use App\Enums\DirecaoIntegracao;
use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Enums\StatusIntegracao;
use App\Http\Controllers\Controller;
use App\Integrations\Uau\Conciliacao;
use App\Integrations\Uau\OperacaoUau;
use App\Integrations\Uau\SincronizacaoEmAndamento;
use App\Models\IntegracaoLog;
use App\Models\Sincronizacao;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Monitoramento das integrações (fatia 8): lista filtrável de integracao_logs,
 * detalhe em modal e reprocessamento de falhas; quadro das sincronizações e
 * botão "Sincronizar agora" (fatia 6).
 */
class IntegracaoController extends Controller
{
    /** Período padrão do filtro, em dias (inclui hoje), como na auditoria. */
    public const DIAS_PADRAO = 7;

    public const SITUACOES = [
        'sucesso' => 'Sucesso',
        'sem-solucao' => 'Erro sem solução',
        'resolvida' => 'Erro resolvido',
    ];

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'executado_em', 'name' => 'executado_em', 'title' => 'Data', 'class' => 'text-nowrap'],
            ['data' => 'operacao', 'name' => 'operacao', 'title' => 'Operação'],
            ['data' => 'objeto', 'name' => 'objeto', 'title' => 'Objeto', 'orderable' => false],
            ['data' => 'situacao', 'name' => 'status', 'title' => 'Situação'],
            ['data' => 'http_status', 'name' => 'http_status', 'title' => 'HTTP'],
            ['data' => 'duracao_ms', 'name' => 'duracao_ms', 'title' => 'Duração'],
            ['data' => 'tentativas', 'name' => 'tentativas', 'title' => 'Tentativa'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'exportable' => false, 'class' => 'td-actions text-nowrap'],
        ])->ajaxWithForm(route('integracao.index'), '#filtros-integracao')->parameters([
            'searching' => false,
            'order' => [[0, 'desc']],
            'language' => ['url' => url('js/datatable.json')],
            'responsive' => true,
            'scrollX' => true,
            'pageLength' => 25,
        ]);

        $sincronizacoes = Sincronizacao::with('criadoPor')->latest('iniciada_em')->limit(5)->get();

        return view('integracoes.index', [
            'html' => $html,
            'sincronizacoes' => $sincronizacoes,
            'emAndamento' => $sincronizacoes->contains(fn (Sincronizacao $s) => $s->status === StatusSincronizacao::EmAndamento),
            'situacoes' => self::SITUACOES,
            'operacoes' => OperacaoUau::cases(),
            'direcoes' => DirecaoIntegracao::cases(),
            // Os filtros aceitam valores na URL (link do indicador do topo).
            'filtros' => [
                'de' => $request->query('de', now()->subDays(self::DIAS_PADRAO - 1)->toDateString()),
                'ate' => $request->query('ate', now()->toDateString()),
                'situacao' => $request->query('situacao'),
                'operacao' => $request->query('operacao'),
                'direcao' => $request->query('direcao'),
            ],
        ]);
    }

    public function show($id)
    {
        $registro = IntegracaoLog::with('entidade')->findOrFail($id);

        return view('integracoes.show', [
            'registro' => $registro,
            'resolvidaPor' => $registro->resolvidaPor(),
        ]);
    }

    /** "Sincronizar agora" (D6.3 da fatia 6): a mesma conciliação do agendamento, uma por vez. */
    public function sincronizar()
    {
        try {
            $sincronizacao = Conciliacao::disparar(OrigemSincronizacao::Manual);
        } catch (SincronizacaoEmAndamento $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        Auditoria::registrar('sincronizacao-solicitada', $sincronizacao);

        return response()->json([
            'success' => true,
            'message' => 'Sincronização enviada para a fila: empresas e obras primeiro, depois as consultas de pessoas.',
        ]);
    }

    public function reprocessar($id, ReprocessarFalha $reprocessar)
    {
        $log = IntegracaoLog::findOrFail($id);

        try {
            return response()->json(['success' => true, 'message' => $reprocessar->executar($log)]);
        } catch (NaoReprocessavel $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function dados(Request $request)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'situacao' => ['nullable', Rule::in(array_keys(self::SITUACOES))],
            'operacao' => ['nullable', Rule::enum(OperacaoUau::class)],
            'direcao' => ['nullable', Rule::enum(DirecaoIntegracao::class)],
        ]);

        // Sem o parâmetro "de", vale o período padrão; enviado vazio, sem limite.
        $de = $request->exists('de') ? ($filtros['de'] ?? null) : now()->subDays(self::DIAS_PADRAO - 1)->toDateString();

        $query = IntegracaoLog::query()
            ->with('entidade')
            ->comResolucao()
            ->when($de, fn ($q, $data) => $q->where('executado_em', '>=', now()->parse($data)->startOfDay()))
            ->when($filtros['ate'] ?? null, fn ($q, $data) => $q->where('executado_em', '<=', now()->parse($data)->endOfDay()))
            ->when($filtros['operacao'] ?? null, fn ($q, $operacao) => $q->where('operacao', $operacao))
            ->when($filtros['direcao'] ?? null, fn ($q, $direcao) => $q->where('direcao', $direcao))
            ->when($filtros['situacao'] ?? null, fn ($q, $situacao) => match ($situacao) {
                'sucesso' => $q->where('status', StatusIntegracao::Sucesso),
                'sem-solucao' => $q->semSolucao(),
                'resolvida' => $q->resolvidas(),
            });

        return DataTables::of($query)
            ->editColumn('executado_em', fn (IntegracaoLog $log) => $log->executado_em->format('d/m/Y H:i:s.v'))
            ->editColumn('operacao', fn (IntegracaoLog $log) => OperacaoUau::tryFrom($log->operacao)?->rotulo() ?? $log->operacao)
            ->addColumn('objeto', fn (IntegracaoLog $log) => $log->descreverEntidade() ?? '—')
            ->addColumn('situacao', fn (IntegracaoLog $log) => view('integracoes.situacao', ['log' => $log]))
            ->editColumn('http_status', fn (IntegracaoLog $log) => $log->http_status ?? '—')
            ->editColumn('duracao_ms', fn (IntegracaoLog $log) => $log->duracao_ms === null ? '—' : "{$log->duracao_ms} ms")
            ->addColumn('action', fn (IntegracaoLog $log) => view('integracoes.action', ['log' => $log]))
            ->rawColumns(['situacao', 'action'])
            ->only(['executado_em', 'operacao', 'objeto', 'situacao', 'http_status', 'duracao_ms', 'tentativas', 'action'])
            ->make(true);
    }
}

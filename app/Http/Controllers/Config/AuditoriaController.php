<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Tela de auditoria, só leitura: lista filtrável e detalhe em modal.
 */
class AuditoriaController extends Controller
{
    /** Período padrão do filtro, em dias (inclui hoje). */
    public const DIAS_PADRAO = 7;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Data', 'class' => 'text-nowrap'],
            ['data' => 'usuario', 'name' => 'usuario', 'title' => 'Usuário', 'orderable' => false],
            ['data' => 'acao', 'name' => 'event', 'title' => 'Ação'],
            ['data' => 'objeto', 'name' => 'objeto', 'title' => 'Objeto', 'orderable' => false],
            ['data' => 'alteracoes', 'name' => 'alteracoes', 'title' => 'Alterações', 'orderable' => false],
            [
                'title' => '',
                'data' => 'action',
                'name' => 'action',
                'orderable' => false,
                'exportable' => false,
                'class' => 'td-actions',
            ],
        ])->ajaxWithForm(route('auditoria.index'), '#filtros-auditoria')->parameters([
            'searching' => false,
            'order' => [[0, 'desc']],
            'language' => [
                'url' => url('js/datatable.json'),
            ],
            'responsive' => true,
            'scrollX' => true,
            'pageLength' => 25,
        ]);

        return view('config.auditoria.index', [
            'html' => $html,
            'usuarios' => User::withTrashed()->orderBy('name')->get(['id', 'name', 'ativo', 'deleted_at']),
            'acoes' => Auditoria::ACOES,
            'dePadrao' => now()->subDays(self::DIAS_PADRAO - 1)->toDateString(),
            'atePadrao' => now()->toDateString(),
        ]);
    }

    public function show($id)
    {
        $registro = Activity::with(['causer', 'subject'])->findOrFail($id);

        return view('config.auditoria.show', [
            'registro' => $registro,
            'alteracoes' => Auditoria::alteracoes($registro),
        ]);
    }

    private function dados(Request $request)
    {
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'usuario' => ['nullable', 'integer'],
            'acao' => ['nullable', Rule::in(array_keys(Auditoria::ACOES))],
        ]);

        // Sem o parâmetro "de", vale o período padrão; enviado vazio, sem limite.
        $de = $request->exists('de') ? ($filtros['de'] ?? null) : now()->subDays(self::DIAS_PADRAO - 1)->toDateString();

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->where('log_name', Auditoria::LOG)
            ->when($de, fn ($q, $data) => $q->where('created_at', '>=', now()->parse($data)->startOfDay()))
            ->when($filtros['ate'] ?? null, fn ($q, $data) => $q->where('created_at', '<=', now()->parse($data)->endOfDay()))
            ->when($filtros['usuario'] ?? null, fn ($q, $id) => $q
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $id))
            ->when($filtros['acao'] ?? null, fn ($q, $acao) => $q->where('event', $acao));

        return DataTables::of($query)
            ->editColumn('created_at', fn (Activity $registro) => $registro->created_at->format('d/m/Y H:i:s'))
            ->addColumn('usuario', fn (Activity $registro) => Auditoria::descreverAutor($registro))
            ->addColumn('acao', fn (Activity $registro) => Auditoria::rotulo($registro->event))
            ->addColumn('objeto', fn (Activity $registro) => Auditoria::descreverObjeto($registro) ?? '—')
            ->addColumn('alteracoes', fn (Activity $registro) => Auditoria::resumirAlteracoes($registro) ?: '—')
            ->addColumn('action', fn (Activity $registro) => view('config.auditoria.action', ['registro' => $registro]))
            ->rawColumns(['action'])
            ->make(true);
    }
}

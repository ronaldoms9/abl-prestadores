<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Cadastros\SalvarColaborador;
use App\Actions\Equipes\EncerrarVinculo;
use App\Actions\Equipes\VincularColaborador;
use App\Enums\StatusColaborador;
use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Empresa;
use App\Models\Equipe;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\VinculoSugerido;
use App\Support\Auditoria;
use App\Support\Documento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Colaboradores: a PF ligada à PJ, ao cargo, à empresa, à obra e às equipes (E2). */
class ColaboradorController extends Controller
{
    use ValidaFormulario;

    public const SITUACOES = ['abertos' => 'Em pré-admissão e ativos', 'pre_admissao' => 'Pré-admissão', 'ativo' => 'Ativos', 'desligado' => 'Desligados', 'todos' => 'Todos'];

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Colaborador', 'orderable' => false],
            ['data' => 'pessoa_juridica', 'name' => 'pessoa_juridica', 'title' => 'Pessoa jurídica', 'orderable' => false],
            ['data' => 'cargo', 'name' => 'cargo', 'title' => 'Cargo', 'orderable' => false],
            ['data' => 'obra', 'name' => 'obra', 'title' => 'Obra', 'orderable' => false],
            ['data' => 'situacao', 'name' => 'status', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('colaborador.index'), '#filtros-cadastro')->parameters([
            'searching' => false,
            'ordering' => false,
            'language' => ['url' => url('js/datatable.json')],
            'responsive' => true,
            'pageLength' => 25,
        ]);

        return view('cadastros.index', [
            'titulo' => 'Colaboradores',
            'placeholder' => 'Nome, CPF, razão social ou CNPJ',
            'aviso' => 'O gestor vê só os colaboradores das equipes que gere hoje. A admissão (contrato e checklist) é da Entrega 3.',
            'html' => $html,
            'situacoes' => self::SITUACOES,
            'novo' => auth()->user()->can('colaborador-create') ? ['rota' => route('colaborador.form'), 'rotulo' => 'Novo colaborador'] : null,
        ]);
    }

    public function show($id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())
            ->with([
                'pessoaFisica', 'pessoaJuridica', 'cargo', 'empresa', 'obra',
                'vinculosEquipe' => fn ($q) => $q->orderByRaw('fim is not null')->orderByDesc('inicio'),
                'vinculosEquipe.equipe',
                'activities' => fn ($q) => $q->latest('id')->limit(15),
                'activities.causer',
            ])
            ->findOrFail($id);

        $equipesLivres = auth()->user()->can('colaborador-edit')
            ? Equipe::ativos()->whereNotIn('id', $colaborador->vinculosEquipe->whereNull('fim')->pluck('equipe_id'))->orderBy('nome')->get()
            : collect();

        return view('colaboradores.show', ['colaborador' => $colaborador, 'equipesLivres' => $equipesLivres]);
    }

    public function form($id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'colaborador-edit' : 'colaborador-create'), 403);

        $colaborador = $id
            ? Colaborador::with(['pessoaFisica', 'pessoaJuridica'])->findOrFail($id)
            : new Colaborador(['status' => StatusColaborador::PreAdmissao]);

        return view('colaboradores.form', [
            'colaborador' => $colaborador,
            'cargos' => Cargo::ativos()->orderBy('nome')->get(),
            'empresas' => Empresa::ativos()->orderBy('razao_social')->get(),
            'obras' => Obra::ativos()->orderBy('descricao')->get(),
        ]);
    }

    public function store(Request $request, SalvarColaborador $salvar, $id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'colaborador-edit' : 'colaborador-create'), 403);
        $colaborador = $id ? Colaborador::findOrFail($id) : null;

        $validos = $this->validarFormulario($request, $request->all(), [
            'pessoa_fisica_id' => [$colaborador ? 'nullable' : 'required', Rule::exists('pessoas_fisicas', 'id')->whereNull('deleted_at')->where('ativo', true)],
            'pessoa_juridica_id' => ['required', Rule::exists('pessoas_juridicas', 'id')->whereNull('deleted_at')->where('ativo', true)],
            'cargo_id' => ['nullable', Rule::exists('cargos', 'id')->whereNull('deleted_at')->where('ativo', true)],
            'empresa_id' => ['nullable', Rule::exists('empresas', 'id')->whereNull('deleted_at')],
            'obra_id' => ['nullable', Rule::exists('obras', 'id')->whereNull('deleted_at')],
        ], [
            'pessoa_fisica_id' => 'pessoa física',
            'pessoa_juridica_id' => 'pessoa jurídica',
            'cargo_id' => 'cargo',
            'empresa_id' => 'empresa',
            'obra_id' => 'obra',
        ]);

        try {
            $salvar->executar($colaborador, $validos);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'Colaborador salvo.', 'data' => []]);
    }

    /** PJs sugeridas pela carga da planilha para a PF escolhida, ainda não utilizadas. */
    public function sugestoes(Request $request)
    {
        $pessoaFisicaId = (int) $request->query('pessoa_fisica_id');

        $sugestoes = VinculoSugerido::query()
            ->where('pessoa_fisica_id', $pessoaFisicaId)
            ->whereNull('utilizado_em')
            ->with('pessoaJuridica')
            ->get()
            ->filter(fn (VinculoSugerido $v) => $v->pessoaJuridica?->ativo);

        return response()->json($sugestoes->map(fn (VinculoSugerido $v) => [
            'id' => $v->pessoa_juridica_id,
            'texto' => $v->pessoaJuridica->razao_social.' · '.$v->pessoaJuridica->cnpj_formatado,
        ])->values());
    }

    public function equipeStore(Request $request, VincularColaborador $vincular, $id)
    {
        $colaborador = Colaborador::findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'equipe_id' => ['required', Rule::exists('equipes', 'id')->whereNull('deleted_at')],
            'inicio' => ['nullable', 'date'],
        ], ['equipe_id' => 'equipe', 'inicio' => 'data de início']);

        try {
            $vinculo = $vincular->executar($colaborador, Equipe::findOrFail($validos['equipe_id']), isset($validos['inicio']) ? Carbon::parse($validos['inicio']) : null);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        Auditoria::registrar('colaborador-equipe-vinculado', $colaborador, ['equipe' => $vinculo->equipe->nome ?? Equipe::find($vinculo->equipe_id)->nome, 'inicio' => $vinculo->inicio->toDateString()]);

        return response()->json(['success' => true, 'message' => 'Colaborador incluído na equipe.', 'data' => []]);
    }

    public function equipeEncerrar(Request $request, EncerrarVinculo $encerrar, $id)
    {
        $vinculo = ColaboradorEquipe::with(['colaborador', 'equipe'])->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), ['fim' => ['nullable', 'date']], ['fim' => 'data de fim']);

        try {
            $encerrar->executar($vinculo, isset($validos['fim']) ? Carbon::parse($validos['fim']) : null);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        Auditoria::registrar('colaborador-equipe-encerrado', $vinculo->colaborador, ['equipe' => $vinculo->equipe->nome, 'fim' => $vinculo->fresh()->fim->toDateString()]);

        return response()->json(['success' => true, 'message' => 'Colaborador retirado da equipe.', 'data' => []]);
    }

    private function dados(Request $request)
    {
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:100'],
            'situacao' => ['nullable', Rule::in(array_keys(self::SITUACOES))],
        ]);

        $query = Colaborador::query()
            ->visiveisPara(auth()->user())
            ->with(['pessoaFisica', 'pessoaJuridica', 'cargo', 'obra'])
            ->latest('id');

        match ($filtros['situacao'] ?? 'abertos') {
            'abertos' => $query->whereIn('status', StatusColaborador::abertos()),
            'todos' => $query,
            default => $query->where('status', $filtros['situacao']),
        };

        if (($busca = trim($filtros['busca'] ?? '')) !== '') {
            $digitos = Documento::digitos($busca) ?? '';
            $docs = preg_match('#^[\d.\-/\s]+$#', $busca) && strlen($digitos) >= 3;
            $termo = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca).'%';

            $query->where(function ($w) use ($docs, $digitos, $termo) {
                if ($docs) {
                    $w->whereHas('pessoaFisica', fn ($q) => $q->where('cpf', 'like', $digitos.'%'))
                        ->orWhereHas('pessoaJuridica', fn ($q) => $q->where('cnpj', 'like', $digitos.'%'));

                    return;
                }
                $w->whereHas('pessoaFisica', fn ($q) => $q->whereRaw("nome like ? escape '!'", [$termo]))
                    ->orWhereHas('pessoaJuridica', fn ($q) => $q->whereRaw("razao_social like ? escape '!'", [$termo])->orWhereRaw("nome_fantasia like ? escape '!'", [$termo]));
            });
        }

        return DataTables::of($query)
            ->addColumn('nome', fn (Colaborador $c) => e($c->pessoaFisica->nome).'<br><small class="text-muted">'.$c->pessoaFisica->cpf_formatado.'</small>')
            ->addColumn('pessoa_juridica', fn (Colaborador $c) => e($c->pessoaJuridica->razao_social).'<br><small class="text-muted">'.$c->pessoaJuridica->cnpj_formatado.'</small>')
            ->addColumn('cargo', fn (Colaborador $c) => e($c->cargo?->nome ?? '—'))
            ->addColumn('obra', fn (Colaborador $c) => e($c->obra?->descricao ?? '—'))
            ->addColumn('situacao', fn (Colaborador $c) => '<span class="badge text-bg-'.($c->status === StatusColaborador::Ativo ? 'success' : ($c->status === StatusColaborador::Desligado ? 'secondary' : 'warning')).'">'.e($c->status->rotulo()).'</span>')
            ->addColumn('action', fn (Colaborador $c) => view('colaboradores._acoes', ['colaborador' => $c]))
            ->rawColumns(['nome', 'pessoa_juridica', 'situacao', 'action'])
            ->only(['nome', 'pessoa_juridica', 'cargo', 'obra', 'situacao', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Equipes\AdicionarGestor;
use App\Actions\Equipes\EncerrarVinculo;
use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\User;
use App\Support\Auditoria;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Equipes independentes da obra, com gestores por vigência (README D7). */
class EquipeController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Equipe'],
            ['data' => 'gestores', 'name' => 'gestores', 'title' => 'Gestores atuais', 'orderable' => false, 'searchable' => false],
            ['data' => 'colaboradores', 'name' => 'colaboradores', 'title' => 'Colaboradores', 'orderable' => false, 'searchable' => false, 'class' => 'text-end'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('equipe.index'), '#filtros-cadastro')->parameters($this->parametrosTabela([[0, 'asc']]));

        return $this->telaDeConsulta([
            'titulo' => 'Equipes',
            'placeholder' => 'Nome da equipe',
            'aviso' => 'Equipes são independentes da obra. O gestor vê só as equipes que gere hoje.',
            'html' => $html,
            'novo' => auth()->user()->can('equipe-create') ? ['rota' => route('equipe.form'), 'rotulo' => 'Nova equipe'] : null,
        ]);
    }

    public function show($id)
    {
        $equipe = Equipe::visiveisPara(auth()->user())
            ->with([
                'gestores' => fn ($q) => $q->orderByRaw('fim is not null')->orderByDesc('inicio'),
                'gestores.usuario',
                'vinculosColaborador' => fn ($q) => $q->vigentes(),
                'vinculosColaborador.colaborador.pessoaFisica',
            ])
            ->findOrFail($id);

        $candidatos = auth()->user()->can('equipe-edit')
            ? User::ativos()->orderBy('name')->get()->filter(fn (User $u) => $u->can('equipe-gerir'))->values()
            : collect();

        return view('equipes.show', ['equipe' => $equipe, 'candidatos' => $candidatos]);
    }

    public function form($id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'equipe-edit' : 'equipe-create'), 403);

        return view('equipes.form', ['equipe' => $id ? Equipe::findOrFail($id) : new Equipe]);
    }

    public function store(Request $request, $id = 0)
    {
        abort_unless(auth()->user()->can($id ? 'equipe-edit' : 'equipe-create'), 403);
        $equipe = $id ? Equipe::findOrFail($id) : new Equipe;

        $validos = $this->validarFormulario($request, $request->all(), [
            'nome' => ['required', 'string', 'max:255', Rule::unique('equipes', 'nome')->ignore($equipe->id)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'ativo' => ['nullable', 'boolean'],
        ], ['nome' => 'nome da equipe', 'descricao' => 'descrição']);

        $equipe->fill([
            'nome' => trim($validos['nome']),
            'descricao' => $validos['descricao'] ?? null,
            'ativo' => $validos['ativo'] ?? $equipe->ativo ?? true,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Equipe salva.', 'data' => []]);
    }

    public function delete($id)
    {
        $equipe = Equipe::findOrFail($id);

        $emUso = $equipe->vinculosColaborador()->vigentes()->exists() || $equipe->gestores()->vigentes()->exists();
        if ($emUso) {
            return response()->json(['success' => false, 'message' => 'A equipe tem gestores ou colaboradores vigentes. Encerre os vínculos antes de excluir.', 'data' => null], 400);
        }

        $equipe->delete();

        return response()->json(['success' => true, 'message' => 'Equipe excluída.', 'data' => null], 201);
    }

    public function gestorStore(Request $request, AdicionarGestor $adicionar, $id)
    {
        $equipe = Equipe::findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'inicio' => ['nullable', 'date'],
        ], ['user_id' => 'gestor', 'inicio' => 'data de início']);

        try {
            $gestor = $adicionar->executar($equipe, User::findOrFail($validos['user_id']), isset($validos['inicio']) ? Carbon::parse($validos['inicio']) : null);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        Auditoria::registrar('equipe-gestor-adicionado', $equipe, ['gestor' => $gestor->usuario->name ?? User::find($gestor->user_id)->name, 'inicio' => $gestor->inicio->toDateString()]);

        return response()->json(['success' => true, 'message' => 'Gestor adicionado.', 'data' => []]);
    }

    public function gestorEncerrar(Request $request, EncerrarVinculo $encerrar, $id)
    {
        $gestor = EquipeGestor::with(['equipe', 'usuario'])->findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), ['fim' => ['nullable', 'date']], ['fim' => 'data de fim']);

        try {
            $encerrar->executar($gestor, isset($validos['fim']) ? Carbon::parse($validos['fim']) : null);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        Auditoria::registrar('equipe-gestor-encerrado', $gestor->equipe, ['gestor' => $gestor->usuario->name, 'fim' => $gestor->fresh()->fim->toDateString()]);

        return response()->json(['success' => true, 'message' => 'Gestor retirado da equipe.', 'data' => []]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(Equipe::query()->visiveisPara(auth()->user()), $request, null, ['nome'])
            ->with(['gestores' => fn ($q) => $q->vigentes(), 'gestores.usuario', 'vinculosColaborador' => fn ($q) => $q->vigentes()]);

        return DataTables::of($query)
            ->addColumn('gestores', fn (Equipe $e) => $e->gestores->map(fn ($g) => $g->usuario->name)->implode(', ') ?: '—')
            ->addColumn('colaboradores', fn (Equipe $e) => $e->vinculosColaborador->count())
            ->addColumn('situacao', fn (Equipe $e) => view('cadastros._situacao', ['ativo' => $e->ativo]))
            ->addColumn('action', fn (Equipe $e) => view('equipes._acoes', ['equipe' => $e]))
            ->rawColumns(['situacao', 'action'])
            ->only(['nome', 'gestores', 'colaboradores', 'situacao', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Cadastros\SalvarPessoaFisica;
use App\Http\Controllers\Controller;
use App\Models\DocumentoTipo;
use App\Models\PessoaFisica;
use App\Rules\Cpf;
use App\Support\Documento;
use App\Support\RegrasCadastro;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta (fatia 7) e cadastro (E2) de pessoas físicas. */
class PessoaFisicaController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'cpf', 'name' => 'cpf', 'title' => 'CPF', 'class' => 'text-nowrap'],
            ['data' => 'nome', 'name' => 'nome', 'title' => 'Nome'],
            ['data' => 'email', 'name' => 'email', 'title' => 'E-mail'],
            ['data' => 'telefone', 'name' => 'telefone', 'title' => 'Telefone', 'orderable' => false],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'sincronizado_em', 'name' => 'sincronizado_em', 'title' => 'Sincronizado em', 'class' => 'text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('pessoa-fisica.index'), '#filtros-cadastro')->parameters($this->parametrosTabela());

        return $this->telaDeConsulta([
            'titulo' => 'Pessoas físicas',
            'placeholder' => 'Nome ou CPF, com ou sem máscara',
            'html' => $html,
            'aviso' => 'Pessoas vindas do UAU se atualizam na sincronização diária; as cadastradas aqui ficam só neste sistema até a gravação no UAU ser ligada.',
            'novo' => auth()->user()->can('pessoa-create') ? ['rota' => route('pessoa-fisica.form'), 'rotulo' => 'Nova pessoa física'] : null,
        ]);
    }

    public function form($id = 0)
    {
        $pessoa = $id ? PessoaFisica::with('dadosBancarios')->findOrFail($id) : new PessoaFisica;
        $this->autorizarEscrita((bool) $id);

        return view('cadastros.pessoas-fisicas.form', ['pessoa' => $pessoa]);
    }

    public function store(Request $request, SalvarPessoaFisica $salvar, $id = 0)
    {
        $this->autorizarEscrita((bool) $id);
        $pessoa = $id ? PessoaFisica::findOrFail($id) : null;

        $dados = $request->all();
        $dados['cpf'] = Documento::digitos($dados['cpf'] ?? null);

        $validos = $this->validarFormulario($request, $dados, [
            'cpf' => ['required', new Cpf, Rule::unique('pessoas_fisicas', 'cpf')->ignore($pessoa?->id)],
            'nome' => ['required', 'string', 'max:255'],
            'nascimento' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'regex:/^[\d\s().+-]{8,20}$/'],
            'ativo' => ['nullable', 'boolean'],
        ] + RegrasCadastro::endereco() + RegrasCadastro::dadosBancarios(), [
            'cpf' => 'CPF',
            'nome' => 'nome',
            'nascimento' => 'nascimento',
            'email' => 'e-mail',
            'telefone' => 'telefone',
        ]);

        $erros = RegrasCadastro::errosChavePix($validos['bancos'] ?? []);
        abort_if($erros !== [], response()->json(['success' => false, 'message' => implode(' ', $erros), 'data' => null], 422));

        $salvar->executar($pessoa, $validos);

        return response()->json(['success' => true, 'message' => 'Pessoa física salva.', 'data' => []]);
    }

    /** Busca por nome ou CPF, para escolher a pessoa física responsável de uma PJ. */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $digitos = Documento::digitos($q);

        $pessoas = PessoaFisica::query()
            ->ativos()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q, $digitos) {
                $w->where('nome', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%');
                if ($digitos !== null && strlen($digitos) >= 3) {
                    $w->orWhere('cpf', 'like', $digitos.'%');
                }
            }))
            ->orderBy('nome')
            ->limit(15)
            ->get(['id', 'nome', 'cpf']);

        return response()->json($pessoas->map(fn (PessoaFisica $p) => [
            'id' => $p->id,
            'texto' => $p->nome.' · '.$p->cpf_formatado,
        ])->values());
    }

    private function autorizarEscrita(bool $edicao): void
    {
        abort_unless(auth()->user()->can($edicao ? 'pessoa-edit' : 'pessoa-create'), 403);
    }

    public function show($id)
    {
        $usuario = auth()->user();

        return view('cadastros.pessoas-fisicas.show', [
            'pessoa' => PessoaFisica::query()
                ->when($usuario->can('documento-list'), fn ($q) => $q->with(['documentos' => fn ($d) => $d->orderByRaw('validade is null')->orderBy('validade'), 'documentos.tipoDocumento']))
                ->with(['pessoasJuridicasSugeridas', 'responsabilidades' => fn ($q) => $q->vigentes(), 'responsabilidades.pessoaJuridica', 'dadosBancarios', 'activities' => fn ($q) => $q->latest('id')->limit(15), 'activities.causer'])
                ->findOrFail($id),
            'tiposDocumento' => $usuario->can('documento-edit') ? DocumentoTipo::paraDono('pessoa-fisica')->orderBy('nome')->get() : null,
        ]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(PessoaFisica::query(), $request, 'cpf', ['nome']);

        return DataTables::of($query)
            ->editColumn('cpf', fn (PessoaFisica $pessoa) => $pessoa->cpf_formatado)
            ->editColumn('email', fn (PessoaFisica $pessoa) => $pessoa->email ?? '—')
            ->editColumn('telefone', fn (PessoaFisica $pessoa) => $pessoa->telefone ?? '—')
            ->addColumn('situacao', fn (PessoaFisica $pessoa) => view('cadastros._situacao', ['ativo' => $pessoa->ativo]))
            ->editColumn('sincronizado_em', fn (PessoaFisica $pessoa) => $pessoa->sincronizado_em?->format('d/m/Y H:i') ?? '—')
            ->addColumn('action', fn (PessoaFisica $pessoa) => view('cadastros._detalhes', [
                'url' => route('pessoa-fisica.show', $pessoa->id),
                'editar' => auth()->user()->can('pessoa-edit') ? route('pessoa-fisica.form', $pessoa->id) : null,
            ]))
            ->rawColumns(['situacao', 'action'])
            ->only(['cpf', 'nome', 'email', 'telefone', 'situacao', 'sincronizado_em', 'action'])
            ->make(true);
    }
}

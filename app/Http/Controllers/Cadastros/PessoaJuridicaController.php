<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Cadastros\SalvarPessoaJuridica;
use App\Http\Controllers\Controller;
use App\Models\PessoaJuridica;
use App\Rules\Cnpj;
use App\Support\Documento;
use App\Support\RegrasCadastro;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder;

/** Consulta (fatia 7) e cadastro (E2) de pessoas jurídicas. */
class PessoaJuridicaController extends Controller
{
    use FiltraCadastro, ValidaFormulario;

    public const REGIMES = ['MEI', 'Simples Nacional', 'Lucro Presumido', 'Lucro Real'];

    public function index(Request $request, Builder $builder)
    {
        if ($request->ajax()) {
            return $this->dados($request);
        }

        $html = $builder->columns([
            ['data' => 'cnpj', 'name' => 'cnpj', 'title' => 'CNPJ', 'class' => 'text-nowrap'],
            ['data' => 'razao_social', 'name' => 'razao_social', 'title' => 'Razão social'],
            ['data' => 'nome_fantasia', 'name' => 'nome_fantasia', 'title' => 'Nome fantasia'],
            ['data' => 'situacao', 'name' => 'ativo', 'title' => 'Situação'],
            ['data' => 'sincronizado_em', 'name' => 'sincronizado_em', 'title' => 'Sincronizado em', 'class' => 'text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'orderable' => false, 'class' => 'td-actions'],
        ])->ajaxWithForm(route('pessoa-juridica.index'), '#filtros-cadastro')->parameters($this->parametrosTabela());

        return $this->telaDeConsulta([
            'titulo' => 'Pessoas jurídicas',
            'placeholder' => 'Razão social, nome fantasia ou CNPJ',
            'html' => $html,
            'aviso' => 'Pessoas vindas do UAU se atualizam na sincronização diária; as cadastradas aqui ficam só neste sistema até a gravação no UAU ser ligada.',
            'novo' => auth()->user()->can('pessoa-create') ? ['rota' => route('pessoa-juridica.form'), 'rotulo' => 'Nova pessoa jurídica'] : null,
        ]);
    }

    public function form($id = 0)
    {
        $this->autorizarEscrita((bool) $id);
        $pessoa = $id ? PessoaJuridica::with(['dadosBancarios', 'responsavelAtual.pessoaFisica'])->findOrFail($id) : new PessoaJuridica;

        return view('cadastros.pessoas-juridicas.form', ['pessoa' => $pessoa, 'regimes' => self::REGIMES]);
    }

    public function store(Request $request, SalvarPessoaJuridica $salvar, $id = 0)
    {
        $this->autorizarEscrita((bool) $id);
        $pessoa = $id ? PessoaJuridica::findOrFail($id) : null;

        $dados = $request->all();
        $dados['cnpj'] = Documento::digitos($dados['cnpj'] ?? null);

        $validos = $this->validarFormulario($request, $dados, [
            'cnpj' => ['required', new Cnpj, Rule::unique('pessoas_juridicas', 'cnpj')->ignore($pessoa?->id)],
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'regime_tributario' => ['nullable', Rule::in(self::REGIMES)],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'regex:/^[\d\s().+-]{8,20}$/'],
            'responsavel_id' => ['required', Rule::exists('pessoas_fisicas', 'id')->whereNull('deleted_at')],
            'ativo' => ['nullable', 'boolean'],
        ] + RegrasCadastro::endereco() + RegrasCadastro::dadosBancarios(), [
            'cnpj' => 'CNPJ',
            'razao_social' => 'razão social',
            'nome_fantasia' => 'nome fantasia',
            'regime_tributario' => 'regime tributário',
            'inscricao_municipal' => 'inscrição municipal',
            'inscricao_estadual' => 'inscrição estadual',
            'email' => 'e-mail',
            'telefone' => 'telefone',
            'responsavel_id' => 'pessoa física responsável',
        ]);

        $erros = RegrasCadastro::errosChavePix($validos['bancos'] ?? []);
        abort_if($erros !== [], response()->json(['success' => false, 'message' => implode(' ', $erros), 'data' => null], 422));

        $salvar->executar($pessoa, $validos);

        return response()->json(['success' => true, 'message' => 'Pessoa jurídica salva.', 'data' => []]);
    }

    /** Busca por razão social, nome fantasia ou CNPJ, para escolher a PJ de um colaborador. */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $digitos = Documento::digitos($q);
        $escapado = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';

        $pessoas = PessoaJuridica::query()
            ->ativos()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($escapado, $digitos, $q) {
                $w->whereRaw("razao_social like ? escape '!'", [$escapado])->orWhereRaw("nome_fantasia like ? escape '!'", [$escapado]);
                if ($digitos !== null && strlen($digitos) >= 3 && preg_match('#^[\d.\-/\s]+$#', $q)) {
                    $w->orWhere('cnpj', 'like', $digitos.'%');
                }
            }))
            ->orderBy('razao_social')
            ->limit(15)
            ->get(['id', 'razao_social', 'cnpj']);

        return response()->json($pessoas->map(fn (PessoaJuridica $p) => [
            'id' => $p->id,
            'texto' => $p->razao_social.' · '.$p->cnpj_formatado,
        ])->values());
    }

    private function autorizarEscrita(bool $edicao): void
    {
        abort_unless(auth()->user()->can($edicao ? 'pessoa-edit' : 'pessoa-create'), 403);
    }

    public function show($id)
    {
        return view('cadastros.pessoas-juridicas.show', [
            'pessoa' => PessoaJuridica::with(['pessoasFisicasSugeridas', 'responsavelAtual.pessoaFisica', 'dadosBancarios', 'activities' => fn ($q) => $q->latest('id')->limit(15), 'activities.causer'])->findOrFail($id),
        ]);
    }

    private function dados(Request $request)
    {
        $query = $this->filtrarCadastro(PessoaJuridica::query(), $request, 'cnpj', ['razao_social', 'nome_fantasia']);

        return DataTables::of($query)
            ->editColumn('cnpj', fn (PessoaJuridica $pessoa) => $pessoa->cnpj_formatado)
            ->editColumn('nome_fantasia', fn (PessoaJuridica $pessoa) => $pessoa->nome_fantasia ?? '—')
            ->addColumn('situacao', fn (PessoaJuridica $pessoa) => view('cadastros._situacao', ['ativo' => $pessoa->ativo]))
            ->editColumn('sincronizado_em', fn (PessoaJuridica $pessoa) => $pessoa->sincronizado_em?->format('d/m/Y H:i') ?? '—')
            ->addColumn('action', fn (PessoaJuridica $pessoa) => view('cadastros._detalhes', [
                'url' => route('pessoa-juridica.show', $pessoa->id),
                'editar' => auth()->user()->can('pessoa-edit') ? route('pessoa-juridica.form', $pessoa->id) : null,
            ]))
            ->rawColumns(['situacao', 'action'])
            ->only(['cnpj', 'razao_social', 'nome_fantasia', 'situacao', 'sincronizado_em', 'action'])
            ->make(true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Documentos\DocumentosAVencer;
use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Dinheiro;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Painel inicial. Cada bloco só aparece para quem tem a permissão da tela
     * de onde o dado vem, e a contagem de colaboradores respeita a visibilidade
     * do gestor (README D7).
     */
    public function index(DocumentosAVencer $documentos, SituacaoQualificacoes $qualificacoes, \App\Services\Painel\IndicadoresPainel $indicadores)
    {
        $user = auth()->user();

        // Quem só tem o portal (colaborador) entra direto nele.
        if ($user->can('portal-acessar') && ! $user->can('colaborador-list')) {
            return redirect()->route('portal.index');
        }

        $hoje = now();
        $dados = [];

        if ($user->can('colaborador-list')) {
            $escopo = fn () => Colaborador::visiveisPara($user);
            $dados['colaboradores'] = [
                'ativos' => $escopo()->where('status', StatusColaborador::Ativo)->count(),
                'pre_admissao' => $escopo()->where('status', StatusColaborador::PreAdmissao)->count(),
                'desligados' => $escopo()->where('status', StatusColaborador::Desligado)->count(),
            ];
            $dados['por_cargo'] = $escopo()
                ->whereIn('status', StatusColaborador::abertos())
                ->join('cargos', 'cargos.id', '=', 'colaboradores.cargo_id')
                ->selectRaw('cargos.nome as cargo, count(*) as total')
                ->groupBy('cargos.nome')
                ->orderByDesc('total')
                ->orderBy('cargos.nome')
                ->get();
        }

        if ($user->can('remuneracao-list') && isset($dados['colaboradores'])) {
            $centavos = Dinheiro::centavos((string) Remuneracao::query()
                ->vigentes()
                ->whereIn('colaborador_id', Colaborador::visiveisPara($user)->where('status', StatusColaborador::Ativo)->select('colaboradores.id'))
                ->sum('valor_mensal'));
            $dados['remuneracao_mensal'] = Dinheiro::formatar($centavos);
        }

        if ($user->can('documento-list')) {
            $dados['documentos'] = $documentos->consultar((int) config('cadastros.alertas.documentos_dias'), $hoje);
        }

        if ($user->can('qualificacao-list')) {
            $dias = (int) config('cadastros.alertas.qualificacoes_dias');
            $dados['qualificacoes'] = $qualificacoes->analisar($hoje, $dias, Colaborador::visiveisPara($user));
        }

        $dados['indicadores'] = $indicadores->para($user, $hoje);

        return view('dashboard', ['d' => $dados]);
    }
}

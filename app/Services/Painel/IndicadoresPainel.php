<?php

namespace App\Services\Painel;

use App\Actions\Contratos\ContratosAVencer;
use App\Actions\Ferias\FeriasAVencer;
use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\ColaboradorObra;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Models\IntegracaoLog;
use App\Models\Obra;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/** Indicadores do painel por perfil (E6, fatia 1). Cada bloco depende da permissão da tela de origem. */
class IndicadoresPainel
{
    public function __construct(private readonly ContratosAVencer $contratos, private readonly FeriasAVencer $ferias)
    {
    }

    /** @return array<string, mixed> */
    public function para(User $usuario, CarbonInterface $hoje): array
    {
        $segundos = (int) config('cadastros.painel.cache_segundos');
        $calcular = fn () => $this->calcular($usuario, $hoje);

        return $segundos > 0
            ? Cache::remember("painel:{$usuario->id}:{$hoje->format('Y-m-d')}", $segundos, $calcular)
            : $calcular();
    }

    /** @return array<string, mixed> */
    private function calcular(User $usuario, CarbonInterface $hoje): array
    {
        $dados = [];

        if ($usuario->can('colaborador-list')) {
            $escopo = fn () => Colaborador::visiveisPara($usuario);
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje->copy()->endOfMonth();

            $dados['movimento'] = [
                'admissoes' => $escopo()->whereDate('data_admissao', '>=', $inicio)->whereDate('data_admissao', '<=', $fim)->count(),
                'desligamentos' => $escopo()->whereDate('data_desligamento', '>=', $inicio)->whereDate('data_desligamento', '<=', $fim)->count(),
            ];

            $dados['sem_equipe'] = $escopo()->where('status', StatusColaborador::Ativo)
                ->whereNotIn('colaboradores.id', ColaboradorEquipe::query()->vigentesEm($hoje)->select('colaborador_id'))
                ->count();

            // Colaborador em várias obras conta em cada uma; sem rateio vigente, vale a obra do cadastro.
            $ativos = fn () => $escopo()->where('colaboradores.status', StatusColaborador::Ativo->value);
            $comRateio = ColaboradorObra::query()->vigentesEm($hoje)->whereIn('colaborador_id', $ativos()->select('colaboradores.id'))
                ->selectRaw('obra_id, count(distinct colaborador_id) as total')->groupBy('obra_id')->pluck('total', 'obra_id')->all();
            $semRateio = $ativos()->whereNotIn('colaboradores.id', ColaboradorObra::query()->vigentesEm($hoje)->select('colaborador_id'))
                ->selectRaw('colaboradores.obra_id as obra_id, count(*) as total')->groupBy('colaboradores.obra_id')->pluck('total', 'obra_id')->all();

            $totais = [];
            foreach ([$comRateio, $semRateio] as $grupo) {
                foreach ($grupo as $obraId => $total) {
                    $totais[(string) $obraId] = ($totais[(string) $obraId] ?? 0) + (int) $total;
                }
            }
            $nomes = Obra::query()->whereIn('id', array_filter(array_keys($totais)))->pluck('descricao', 'id');
            $dados['por_obra'] = collect($totais)
                ->map(fn ($total, $obraId) => ['obra' => $obraId === '' ? 'Sem obra' : ($nomes[(int) $obraId] ?? 'Sem obra'), 'total' => $total])
                ->sortBy([['total', 'desc'], ['obra', 'asc']])->values()->all();
        }

        if ($usuario->can('fechamento-list')) {
            $dados['custo'] = $this->custo($hoje);
        }

        if ($usuario->can('nota-list')) {
            $dados['notas_pendentes'] = Fechamento::query()
                ->where('status', StatusFechamento::AguardandoNota->value)
                ->where(fn ($q) => $q->whereDoesntHave('nota')->orWhereHas('nota', fn ($n) => $n->where('status', 'recusada')))
                ->count();
        }

        if ($usuario->can('integracao-list')) {
            $dados['integracoes_com_erro'] = IntegracaoLog::query()->falhas()->count();
        }

        if ($usuario->can('contrato-list')) {
            $r = $this->contratos->consultar(range(0, 30), $hoje);
            $dados['contratos'] = [
                'vencidos' => $r['vencidos']->count(),
                'a_vencer' => $r['marcos']->count(),
                'itens' => $r['vencidos']->concat($r['marcos'])->sortBy('fim')->take(6)->map(fn ($i) => [
                    'colaborador' => $i['contrato']->colaborador->pessoaFisica->nome,
                    'numero' => $i['contrato']->numero,
                    'fim' => $i['fim']->format('d/m/Y'),
                    'dias' => $i['dias'],
                ])->values()->all(),
            ];
        }

        if ($usuario->can('ferias-list')) {
            $dias = (int) (\App\Models\Configuracao::obter('alertas.ferias', [])['dias'] ?? config('cadastros.ferias.alerta_dias'));
            $r = $this->ferias->consultar($dias, $hoje);
            $dados['ferias'] = ['vencidos' => $r['vencidos']->count(), 'a_vencer' => $r['a_vencer']->count(), 'dias' => $dias];
        }

        return $dados;
    }

    /** Custo do mês (última competência aprovada) e a série dos últimos 12 meses. */
    private function custo(CarbonInterface $hoje): array
    {
        $competencias = Competencia::query()
            ->whereIn('status', ['aprovada', 'fechada'])
            ->where('ano_mes', '<=', $hoje->format('Y-m'))
            ->orderByDesc('ano_mes')->limit(12)->get()->reverse()->values();

        $totais = Fechamento::query()
            ->whereIn('competencia_id', $competencias->pluck('id'))
            ->selectRaw('competencia_id, sum(total_liquido) as total')
            ->groupBy('competencia_id')->pluck('total', 'competencia_id');

        $serie = $competencias->map(fn (Competencia $c) => ['mes' => substr($c->ano_mes, 5, 2).'/'.substr($c->ano_mes, 0, 4), 'total' => round((float) ($totais[$c->id] ?? 0), 2)])->all();
        $ultima = $competencias->last();

        return [
            'ultimo_mes' => $ultima === null ? null : substr($ultima->ano_mes, 5, 2).'/'.substr($ultima->ano_mes, 0, 4),
            'ultimo_total' => $ultima === null ? null : round((float) ($totais[$ultima->id] ?? 0), 2),
            'serie' => $serie,
        ];
    }
}

<?php

namespace App\Services\Relatorios;

use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\User;

class FeriasESaldos implements Relatorio
{
    public function slug(): string
    {
        return 'ferias-e-saldos';
    }

    public function titulo(): string
    {
        return 'Férias e saldos';
    }

    public function descricao(): string
    {
        return 'Saldo de férias dos colaboradores ativos, o limite de gozo mais próximo e os dias já aprovados para gozar.';
    }

    public function permissao(): string
    {
        return 'ferias-list';
    }

    public function filtros(): array
    {
        return [
            'obra_id' => ['Obra', 'obra', null],
            'equipe_id' => ['Equipe', 'equipe', null],
            'saldo_minimo' => ['Saldo mínimo (dias)', 'numero', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        $hoje = now()->startOfDay();

        $colaboradores = Colaborador::visiveisPara($usuario)
            ->where('colaboradores.status', StatusColaborador::Ativo->value)
            ->when($filtros['obra_id'] ?? null, fn ($q, $o) => $q->naObra($o))
            ->when($filtros['equipe_id'] ?? null, fn ($q, $e) => $q->whereIn('colaboradores.id', ColaboradorEquipe::query()->vigentesEm($hoje)->where('equipe_id', $e)->select('colaborador_id')))
            ->with(['pessoaFisica', 'obra', 'feriasPeriodos', 'feriasSolicitacoes' => fn ($q) => $q->where('status', StatusSolicitacaoFerias::Aprovada->value)->whereDate('fim', '>=', $hoje)])
            ->get();

        $dados = $colaboradores->map(function (Colaborador $c) use ($hoje) {
            $comSaldo = $c->feriasPeriodos->filter(fn ($p) => $p->saldo() > 0)->sortBy('limite_gozo');
            $limite = $comSaldo->first()?->limite_gozo;

            return [
                'colaborador' => $c->pessoaFisica->nome,
                'obra' => $c->obra?->descricao ?? '—',
                'saldo' => $c->feriasPeriodos->sum(fn ($p) => $p->saldo()),
                'limite' => $limite?->format('d/m/Y') ?? '—',
                'situacao' => $limite === null ? '—' : ($limite->lt($hoje) ? 'Vencido' : 'No prazo'),
                'a_gozar' => $c->feriasSolicitacoes->sum('dias'),
                '_ordem' => $limite?->timestamp ?? PHP_INT_MAX,
            ];
        })
            ->filter(fn ($l) => $l['saldo'] >= (int) ($filtros['saldo_minimo'] ?? 0))
            ->sortBy([['_ordem', 'asc'], ['colaborador', 'asc']])
            ->map(fn ($l) => array_diff_key($l, ['_ordem' => 1]))->values()->all();

        return new ResultadoRelatorio(
            [
                ['chave' => 'colaborador', 'rotulo' => 'Colaborador', 'tipo' => 'texto'],
                ['chave' => 'obra', 'rotulo' => 'Obra', 'tipo' => 'texto'],
                ['chave' => 'saldo', 'rotulo' => 'Saldo (dias)', 'tipo' => 'numero'],
                ['chave' => 'limite', 'rotulo' => 'Limite de gozo', 'tipo' => 'texto'],
                ['chave' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'texto'],
                ['chave' => 'a_gozar', 'rotulo' => 'Aprovados a gozar (dias)', 'tipo' => 'numero'],
            ],
            $dados,
            $dados === [] ? null : ['colaborador' => 'Total', 'obra' => '', 'saldo' => array_sum(array_column($dados, 'saldo')), 'limite' => '', 'situacao' => '', 'a_gozar' => array_sum(array_column($dados, 'a_gozar'))],
        );
    }
}

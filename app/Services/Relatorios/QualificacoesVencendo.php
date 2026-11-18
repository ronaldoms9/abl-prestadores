<?php

namespace App\Services\Relatorios;

use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Models\Colaborador;
use App\Models\User;

class QualificacoesVencendo implements Relatorio
{
    public function __construct(private readonly SituacaoQualificacoes $situacao)
    {
    }

    public function slug(): string
    {
        return 'qualificacoes-vencendo';
    }

    public function titulo(): string
    {
        return 'Qualificações vencendo';
    }

    public function descricao(): string
    {
        return 'Qualificações obrigatórias do cargo que estão ausentes, vencidas ou vencem na janela.';
    }

    public function permissao(): string
    {
        return 'qualificacao-list';
    }

    public function filtros(): array
    {
        return [
            'dias' => ['Janela (dias)', 'numero', 30],
            'cargo_id' => ['Cargo', 'cargo', null],
            'obra_id' => ['Obra', 'obra', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        $hoje = now()->startOfDay();
        $escopo = Colaborador::visiveisPara($usuario)->when($filtros['obra_id'] ?? null, fn ($q, $o) => $q->naObra($o));

        $linhas = [];
        foreach ($this->situacao->analisar($hoje, (int) ($filtros['dias'] ?? 30), $escopo, $filtros['cargo_id'] ?? null) as $s) {
            $base = ['colaborador' => $s['colaborador']->pessoaFisica->nome, 'cargo' => $s['colaborador']->cargo?->nome ?? '—'];
            foreach ($s['ausentes'] as $tipo) {
                $linhas[] = $base + ['qualificacao' => $tipo->nome, 'situacao' => 'Ausente', 'validade' => '—'];
            }
            foreach ($s['vencidas'] as $q) {
                $linhas[] = $base + ['qualificacao' => $q->tipo->nome, 'situacao' => 'Vencida', 'validade' => $q->data_validade->format('d/m/Y')];
            }
            foreach ($s['a_vencer'] as $q) {
                $linhas[] = $base + ['qualificacao' => $q->tipo->nome, 'situacao' => 'A vencer', 'validade' => $q->data_validade->format('d/m/Y')];
            }
        }

        return new ResultadoRelatorio(
            [
                ['chave' => 'colaborador', 'rotulo' => 'Colaborador', 'tipo' => 'texto'],
                ['chave' => 'cargo', 'rotulo' => 'Cargo', 'tipo' => 'texto'],
                ['chave' => 'qualificacao', 'rotulo' => 'Qualificação', 'tipo' => 'texto'],
                ['chave' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'texto'],
                ['chave' => 'validade', 'rotulo' => 'Validade', 'tipo' => 'texto'],
            ],
            $linhas,
            $linhas === [] ? null : ['colaborador' => 'Total: '.count($linhas).' pendência(s)', 'cargo' => '', 'qualificacao' => '', 'situacao' => '', 'validade' => ''],
        );
    }
}

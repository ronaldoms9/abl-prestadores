<?php

namespace App\Services\Relatorios;

use App\Models\FechamentoRateio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustoPorObra implements Relatorio
{
    public function slug(): string
    {
        return 'custo-por-obra';
    }

    public function titulo(): string
    {
        return 'Custo por obra';
    }

    public function descricao(): string
    {
        return 'Remuneração, benefícios e ajustes das competências aprovadas, somados por obra. Quem atua em mais de uma obra aparece em cada uma, com a parte do rateio.';
    }

    public function permissao(): string
    {
        return 'fechamento-list';
    }

    public function filtros(): array
    {
        return [
            'de' => ['Competência de', 'mes', null],
            'ate' => ['Competência até', 'mes', null],
            'empresa_id' => ['Empresa', 'empresa', null],
            'obra_id' => ['Obra', 'obra', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        // Cada fechamento entra pela parte que cabe a cada obra (rateio gravado no cálculo, E4 fatia 10).
        $linhas = FechamentoRateio::query()
            ->join('fechamentos', 'fechamentos.id', '=', 'fechamento_rateios.fechamento_id')
            ->join('competencias', 'competencias.id', '=', 'fechamentos.competencia_id')
            ->join('colaboradores', 'colaboradores.id', '=', 'fechamentos.colaborador_id')
            ->leftJoin('obras', 'obras.id', '=', 'fechamento_rateios.obra_id')
            ->whereNull('fechamentos.deleted_at')
            ->whereIn('competencias.status', ['aprovada', 'fechada'])
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('competencias.ano_mes', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('competencias.ano_mes', '<=', $ate))
            ->when($filtros['empresa_id'] ?? null, fn ($q, $e) => $q->where('colaboradores.empresa_id', $e))
            ->when($filtros['obra_id'] ?? null, fn ($q, $o) => $q->where('fechamento_rateios.obra_id', $o))
            ->whereIn('colaboradores.id', \App\Models\Colaborador::visiveisPara($usuario)->select('colaboradores.id'))
            ->groupBy('obras.id', 'obras.descricao')
            ->selectRaw("coalesce(obras.descricao, 'Sem obra') as obra, count(distinct fechamentos.colaborador_id) as colaboradores, sum(fechamento_rateios.valor_remuneracao) as remuneracao, sum(fechamento_rateios.total_beneficios) as beneficios, sum(fechamento_rateios.total_ajustes) as ajustes, sum(fechamento_rateios.total_liquido) as liquido")
            ->orderBy('obra')
            ->get();

        $colunas = [
            ['chave' => 'obra', 'rotulo' => 'Obra', 'tipo' => 'texto'],
            ['chave' => 'colaboradores', 'rotulo' => 'Colaboradores', 'tipo' => 'numero'],
            ['chave' => 'remuneracao', 'rotulo' => 'Remuneração', 'tipo' => 'dinheiro'],
            ['chave' => 'beneficios', 'rotulo' => 'Benefícios', 'tipo' => 'dinheiro'],
            ['chave' => 'ajustes', 'rotulo' => 'Ajustes', 'tipo' => 'dinheiro'],
            ['chave' => 'liquido', 'rotulo' => 'Total líquido', 'tipo' => 'dinheiro'],
        ];

        $dados = $linhas->map(fn ($l) => [
            'obra' => $l->obra, 'colaboradores' => (int) $l->colaboradores,
            'remuneracao' => Formato::reais($l->remuneracao), 'beneficios' => Formato::reais($l->beneficios),
            'ajustes' => Formato::reais($l->ajustes), 'liquido' => Formato::reais($l->liquido),
        ])->all();

        $total = ['obra' => 'Total', 'colaboradores' => '', 'remuneracao' => Formato::reais($linhas->sum('remuneracao')), 'beneficios' => Formato::reais($linhas->sum('beneficios')), 'ajustes' => Formato::reais($linhas->sum('ajustes')), 'liquido' => Formato::reais($linhas->sum('liquido'))];

        return new ResultadoRelatorio($colunas, $dados, $dados === [] ? null : $total);
    }
}

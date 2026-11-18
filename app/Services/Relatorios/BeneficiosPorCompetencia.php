<?php

namespace App\Services\Relatorios;

use App\Models\Colaborador;
use App\Models\FechamentoItem;
use App\Models\User;

class BeneficiosPorCompetencia implements Relatorio
{
    public function slug(): string
    {
        return 'beneficios';
    }

    public function titulo(): string
    {
        return 'Benefícios';
    }

    public function descricao(): string
    {
        return 'Valor de cada benefício nas competências aprovadas, a partir da memória de cálculo do fechamento.';
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
            'beneficio_id' => ['Benefício', 'beneficio', null],
            'obra_id' => ['Obra', 'obra', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        $linhas = FechamentoItem::query()
            ->join('fechamentos', 'fechamentos.id', '=', 'fechamento_itens.fechamento_id')
            ->join('competencias', 'competencias.id', '=', 'fechamentos.competencia_id')
            ->join('colaboradores', 'colaboradores.id', '=', 'fechamentos.colaborador_id')
            ->join('colaborador_beneficios', function ($j) {
                $j->on('colaborador_beneficios.id', '=', 'fechamento_itens.origem_id')->where('fechamento_itens.origem_type', '=', 'colaborador-beneficio');
            })
            ->join('beneficios', 'beneficios.id', '=', 'colaborador_beneficios.beneficio_id')
            ->where('fechamento_itens.tipo', 'beneficio')
            ->whereIn('competencias.status', ['aprovada', 'fechada'])
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('competencias.ano_mes', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('competencias.ano_mes', '<=', $ate))
            ->when($filtros['beneficio_id'] ?? null, fn ($q, $b) => $q->where('beneficios.id', $b))
            // Com filtro de obra, entra só a parte do rateio que cabe a ela.
            ->when($filtros['obra_id'] ?? null, fn ($q, $o) => $q->join('fechamento_rateios', fn ($j) => $j->on('fechamento_rateios.fechamento_id', '=', 'fechamentos.id')->where('fechamento_rateios.obra_id', '=', $o)))
            ->whereIn('colaboradores.id', Colaborador::visiveisPara($usuario)->select('colaboradores.id'))
            ->groupBy('beneficios.id', 'beneficios.nome')
            ->selectRaw(($filtros['obra_id'] ?? null) ? 'beneficios.nome as beneficio, count(distinct fechamentos.colaborador_id) as colaboradores, sum(fechamento_itens.valor_total * fechamento_rateios.percentual / 100) as total' : 'beneficios.nome as beneficio, count(distinct fechamentos.colaborador_id) as colaboradores, sum(fechamento_itens.valor_total) as total')
            ->orderBy('beneficios.nome')
            ->get();

        $dados = $linhas->map(fn ($l) => ['beneficio' => $l->beneficio, 'colaboradores' => (int) $l->colaboradores, 'total' => Formato::reais($l->total)])->all();

        return new ResultadoRelatorio(
            [
                ['chave' => 'beneficio', 'rotulo' => 'Benefício', 'tipo' => 'texto'],
                ['chave' => 'colaboradores', 'rotulo' => 'Colaboradores', 'tipo' => 'numero'],
                ['chave' => 'total', 'rotulo' => 'Valor total', 'tipo' => 'dinheiro'],
            ],
            $dados,
            $dados === [] ? null : ['beneficio' => 'Total', 'colaboradores' => '', 'total' => Formato::reais($linhas->sum('total'))],
        );
    }
}

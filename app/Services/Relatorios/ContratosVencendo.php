<?php

namespace App\Services\Relatorios;

use App\Actions\Contratos\ContratosAVencer;
use App\Models\Colaborador;
use App\Models\User;

class ContratosVencendo implements Relatorio
{
    public function __construct(private readonly ContratosAVencer $contratos)
    {
    }

    public function slug(): string
    {
        return 'contratos-vencendo';
    }

    public function titulo(): string
    {
        return 'Contratos vencendo';
    }

    public function descricao(): string
    {
        return 'Contratos já vencidos e os que vencem na janela, considerando as prorrogações por aditivo.';
    }

    public function permissao(): string
    {
        return 'contrato-list';
    }

    public function filtros(): array
    {
        return [
            'dias' => ['Janela (dias)', 'numero', 30],
            'obra_id' => ['Obra', 'obra', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        $r = $this->contratos->consultar(range(0, max(0, (int) ($filtros['dias'] ?? 30))), now());
        $visiveis = Colaborador::visiveisPara($usuario)
            ->when($filtros['obra_id'] ?? null, fn ($q, $o) => $q->naObra($o))
            ->pluck('colaboradores.id')->flip();

        $linhas = $r['vencidos']->concat($r['marcos'])
            ->filter(fn ($i) => $visiveis->has($i['contrato']->colaborador_id))
            ->sortBy('fim')
            ->map(fn ($i) => [
                'colaborador' => $i['contrato']->colaborador->pessoaFisica->nome,
                'contrato' => $i['contrato']->numero ?? '—',
                'fim' => $i['fim']->format('d/m/Y'),
                'situacao' => $i['dias'] < 0 ? 'Vencido há '.abs($i['dias']).' dia(s)' : ($i['dias'] === 0 ? 'Vence hoje' : 'Vence em '.$i['dias'].' dia(s)'),
            ])->values()->all();

        return new ResultadoRelatorio(
            [
                ['chave' => 'colaborador', 'rotulo' => 'Colaborador', 'tipo' => 'texto'],
                ['chave' => 'contrato', 'rotulo' => 'Contrato', 'tipo' => 'texto'],
                ['chave' => 'fim', 'rotulo' => 'Fim da vigência', 'tipo' => 'texto'],
                ['chave' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'texto'],
            ],
            $linhas,
            $linhas === [] ? null : ['colaborador' => 'Total: '.count($linhas).' contrato(s)', 'contrato' => '', 'fim' => '', 'situacao' => ''],
        );
    }
}

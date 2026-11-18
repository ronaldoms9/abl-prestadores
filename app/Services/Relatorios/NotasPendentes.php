<?php

namespace App\Services\Relatorios;

use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Models\User;

class NotasPendentes implements Relatorio
{
    public function slug(): string
    {
        return 'notas-pendentes';
    }

    public function titulo(): string
    {
        return 'Notas pendentes';
    }

    public function descricao(): string
    {
        return 'Fechamentos aprovados e a situação da nota fiscal de cada um (por padrão, tudo que ainda não foi aprovado).';
    }

    public function permissao(): string
    {
        return 'nota-list';
    }

    public function filtros(): array
    {
        return [
            'competencia' => ['Competência', 'mes', null],
            'status' => ['Situação', 'status', 'pendentes'],
            'obra_id' => ['Obra', 'obra', null],
        ];
    }

    public function executar(array $filtros, User $usuario): ResultadoRelatorio
    {
        $status = $filtros['status'] ?? 'pendentes';

        $fechamentos = Fechamento::query()
            ->with(['colaborador.pessoaFisica', 'colaborador.obra', 'competencia', 'nota'])
            ->where('status', StatusFechamento::AguardandoNota->value)
            ->whereHas('competencia', fn ($q) => $q->when($filtros['competencia'] ?? null, fn ($w, $m) => $w->where('ano_mes', $m)))
            ->whereHas('colaborador', fn ($q) => $q->visiveisPara($usuario)->when($filtros['obra_id'] ?? null, fn ($w, $o) => $w->where('colaboradores.obra_id', $o)))
            ->get()
            ->filter(function (Fechamento $f) use ($status) {
                $atual = ($f->nota?->status ?? StatusNota::Aguardando);

                return match ($status) {
                    'pendentes' => $atual !== StatusNota::Aprovada,
                    'todas' => true,
                    default => $atual->value === $status,
                };
            })
            ->sortBy([fn ($a, $b) => strcmp($b->competencia->ano_mes, $a->competencia->ano_mes), fn ($a, $b) => strcmp($a->colaborador->pessoaFisica->nome, $b->colaborador->pessoaFisica->nome)]);

        $dados = $fechamentos->map(fn (Fechamento $f) => [
            'competencia' => Formato::mes($f->competencia->ano_mes),
            'colaborador' => $f->colaborador->pessoaFisica->nome,
            'obra' => $f->colaborador->obra?->descricao ?? '—',
            'esperado' => Formato::reais($f->total_liquido),
            'nota' => $f->nota?->numero ?? '—',
            'situacao' => ($f->nota?->status ?? StatusNota::Aguardando)->rotulo(),
        ])->values()->all();

        return new ResultadoRelatorio(
            [
                ['chave' => 'competencia', 'rotulo' => 'Competência', 'tipo' => 'texto'],
                ['chave' => 'colaborador', 'rotulo' => 'Colaborador', 'tipo' => 'texto'],
                ['chave' => 'obra', 'rotulo' => 'Obra', 'tipo' => 'texto'],
                ['chave' => 'esperado', 'rotulo' => 'Valor esperado', 'tipo' => 'dinheiro'],
                ['chave' => 'nota', 'rotulo' => 'Nota', 'tipo' => 'texto'],
                ['chave' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'texto'],
            ],
            $dados,
            $dados === [] ? null : ['competencia' => 'Total: '.count($dados), 'colaborador' => '', 'obra' => '', 'esperado' => Formato::reais($fechamentos->sum('total_liquido')), 'nota' => '', 'situacao' => ''],
        );
    }
}

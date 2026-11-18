<?php

namespace App\Actions\Ferias;

use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\ColaboradorEquipe;
use App\Models\Colaborador;
use App\Models\FeriasSolicitacao;
use Carbon\CarbonInterface;

/** Quem mais da equipe ou da obra já está de férias (pendentes ou aprovadas) no período. Só informa. */
class ConflitosFerias
{
    /** @return list<string> */
    public function para(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarSolicitacao = null): array
    {
        $equipes = $colaborador->equipesVigentesEm($inicio)->pluck('equipes.id');
        $mesmaEquipe = ColaboradorEquipe::query()
            ->whereIn('equipe_id', $equipes)
            ->where('colaborador_id', '!=', $colaborador->id)
            ->where(fn ($q) => $q->whereDate('inicio', '<=', $fim)->where(fn ($w) => $w->whereNull('fim')->orWhereDate('fim', '>=', $inicio)))
            ->select('colaborador_id');

        $solicitacoes = FeriasSolicitacao::query()
            ->with('colaborador.pessoaFisica')
            ->where(fn ($q) => $q
                ->whereIn('colaborador_id', $mesmaEquipe)
                ->when($colaborador->obra_id !== null, fn ($w) => $w->orWhereIn('colaborador_id', Colaborador::query()
                    ->where('obra_id', $colaborador->obra_id)->where('id', '!=', $colaborador->id)
                    ->where('status', StatusColaborador::Ativo->value)->select('id'))))
            ->whereIn('status', [StatusSolicitacaoFerias::Pendente->value, StatusSolicitacaoFerias::Aprovada->value])
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->when($ignorarSolicitacao, fn ($q) => $q->whereKeyNot($ignorarSolicitacao))
            ->orderBy('inicio')->get();

        return $solicitacoes->map(fn (FeriasSolicitacao $s) => $s->colaborador->pessoaFisica->nome.' ('.$s->inicio->format('d/m').' a '.$s->fim->format('d/m').')')->unique()->values()->all();
    }
}

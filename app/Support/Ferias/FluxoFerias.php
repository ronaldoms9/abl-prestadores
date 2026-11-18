<?php

namespace App\Support\Ferias;

use App\Enums\EscopoFluxoFerias;
use App\Models\Colaborador;
use App\Models\FeriasFluxo;
use Carbon\CarbonInterface;

/**
 * Qual fluxo de aprovação de férias vale para um colaborador (E4, fatia 9): o mais
 * específico que casa (colaborador, função na equipe, equipe, função, padrão). Entre
 * fluxos igualmente específicos vence o de mais níveis (o mais rigoroso), depois o de menor id.
 * Fluxo sem nenhum nível ativo não conta.
 */
class FluxoFerias
{
    public function paraColaborador(Colaborador $colaborador, CarbonInterface $data): FeriasFluxo
    {
        $equipes = $colaborador->vinculosEquipe()->vigentesEm($data)->pluck('equipe_id')->all();

        $fluxos = FeriasFluxo::query()->where('ativo', true)
            ->whereHas('niveis', fn ($q) => $q->where('ativo', true))
            ->withCount(['niveis as niveis_ativos' => fn ($q) => $q->where('ativo', true)])
            ->get();

        $casam = $fluxos->filter(fn (FeriasFluxo $f) => match ($f->escopo) {
            EscopoFluxoFerias::Colaborador => $f->colaborador_id === $colaborador->id,
            EscopoFluxoFerias::EquipeCargo => in_array($f->equipe_id, $equipes, true) && $f->cargo_id === $colaborador->cargo_id,
            EscopoFluxoFerias::Equipe => in_array($f->equipe_id, $equipes, true),
            EscopoFluxoFerias::Cargo => $f->cargo_id !== null && $f->cargo_id === $colaborador->cargo_id,
            EscopoFluxoFerias::Padrao => true,
        });

        $escolhido = $casam
            ->sortBy([
                fn ($a, $b) => $b->escopo->especificidade() <=> $a->escopo->especificidade(),
                fn ($a, $b) => $b->niveis_ativos <=> $a->niveis_ativos,
                fn ($a, $b) => $a->id <=> $b->id,
            ])->first();

        return $escolhido ?? FeriasFluxo::padrao();
    }
}

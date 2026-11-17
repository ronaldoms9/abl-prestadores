<?php

namespace App\Actions\Qualificacoes;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\ColaboradorQualificacao;
use App\Models\QualificacaoTipo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Situação das qualificações obrigatórias do cargo de cada colaborador (E2, fatia 7).
 *
 * Para cada tipo obrigatório do cargo vale a qualificação mais recente
 * (maior data de conclusão): sem nenhuma, está AUSENTE; com validade anterior
 * a hoje, VENCIDA; com validade em até $dias dias, A VENCER. Tipo sem validade
 * nunca vence. Só entram colaboradores em pré-admissão ou ativos, e só as
 * exigências obrigatórias de tipos ativos: o que é opcional na matriz não gera pendência.
 */
class SituacaoQualificacoes
{
    /**
     * @param  Builder<Colaborador>|null  $escopo  Restringe os colaboradores (ex.: os que o gestor vê).
     * @return Collection<int, array{colaborador: Colaborador, ausentes: Collection<int, QualificacaoTipo>, vencidas: Collection<int, ColaboradorQualificacao>, a_vencer: Collection<int, ColaboradorQualificacao>}>
     */
    public function analisar(CarbonInterface $hoje, int $dias, ?Builder $escopo = null, ?int $cargoId = null): Collection
    {
        $colaboradores = ($escopo ?? Colaborador::query())
            ->whereIn('status', StatusColaborador::abertos())
            ->whereNotNull('cargo_id')
            ->when($cargoId, fn ($q) => $q->where('cargo_id', $cargoId))
            ->with(['pessoaFisica', 'cargo.exigencias' => fn ($q) => $q->where('obrigatoria', true), 'cargo.exigencias.tipo', 'qualificacoes.tipo'])
            ->orderBy('id')
            ->get();

        return $colaboradores
            ->map(fn (Colaborador $c) => $this->situacao($c, $hoje->copy()->startOfDay(), $dias))
            ->filter(fn (array $s) => $s['ausentes']->isNotEmpty() || $s['vencidas']->isNotEmpty() || $s['a_vencer']->isNotEmpty())
            ->sortBy(fn (array $s) => $s['colaborador']->pessoaFisica->nome)
            ->values();
    }

    /** @return array{colaborador: Colaborador, ausentes: Collection<int, QualificacaoTipo>, vencidas: Collection<int, ColaboradorQualificacao>, a_vencer: Collection<int, ColaboradorQualificacao>} */
    public function situacao(Colaborador $colaborador, CarbonInterface $hoje, int $dias): array
    {
        $maisRecentes = $colaborador->qualificacoes
            ->groupBy('qualificacao_tipo_id')
            ->map(fn (Collection $g) => $g->sortByDesc(fn (ColaboradorQualificacao $q) => [$q->data_conclusao->timestamp, $q->id])->first());

        $ausentes = collect();
        $vencidas = collect();
        $aVencer = collect();

        foreach ($colaborador->cargo?->exigencias ?? [] as $exigencia) {
            if (! $exigencia->tipo?->ativo) {
                continue;
            }

            $q = $maisRecentes->get($exigencia->qualificacao_tipo_id);

            if ($q === null) {
                $ausentes->push($exigencia->tipo);
            } elseif ($q->data_validade !== null && $q->data_validade->lt($hoje)) {
                $vencidas->push($q);
            } elseif ($q->data_validade !== null && $q->data_validade->lte($hoje->copy()->addDays($dias))) {
                $aVencer->push($q);
            }
        }

        return ['colaborador' => $colaborador, 'ausentes' => $ausentes, 'vencidas' => $vencidas, 'a_vencer' => $aVencer];
    }
}

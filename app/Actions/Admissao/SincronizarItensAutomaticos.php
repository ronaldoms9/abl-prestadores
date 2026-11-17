<?php

namespace App\Actions\Admissao;

use App\Models\AdmissaoChecklist;
use App\Models\Colaborador;
use App\Support\Admissao\ItensAutomaticos;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Grava no checklist do colaborador o resultado atual dos itens automáticos (sem autor: quem marca é o sistema). */
class SincronizarItensAutomaticos
{
    public function __construct(private readonly ItensAutomaticos $automaticos)
    {
    }

    /** @return Collection<int, AdmissaoChecklist> Todas as linhas do colaborador, já atualizadas. */
    public function executar(Colaborador $colaborador, ?CarbonInterface $hoje = null): Collection
    {
        $linhas = AdmissaoChecklist::query()->where('colaborador_id', $colaborador->id)->orderBy('ordem')->orderBy('id')->get();
        $automaticas = $linhas->filter(fn (AdmissaoChecklist $l) => $l->chave !== null);

        if ($automaticas->isEmpty()) {
            return $linhas;
        }

        $resultado = $this->automaticos->avaliar($colaborador, $hoje ?? now());

        foreach ($automaticas as $linha) {
            $ok = $resultado[$linha->chave]['ok'] ?? null;

            if ($ok === null || $ok === $linha->concluido) {
                continue;
            }

            $linha->update(['concluido' => $ok, 'concluido_em' => $ok ? now() : null, 'concluido_por' => null]);
        }

        return $linhas;
    }
}

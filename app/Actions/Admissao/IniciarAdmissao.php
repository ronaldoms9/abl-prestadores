<?php

namespace App\Actions\Admissao;

use App\Enums\StatusColaborador;
use App\Models\AdmissaoChecklist;
use App\Models\AdmissaoChecklistItem;
use App\Models\Colaborador;
use App\Support\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre o checklist do colaborador em pré-admissão com os itens ativos
 * configurados, copiando nome, ordem e obrigatoriedade (mudar a configuração
 * depois não reescreve admissões em andamento). Pode rodar de novo: só
 * acrescenta os itens que ainda não estão na lista, nunca apaga nem desmarca.
 */
class IniciarAdmissao
{
    public function __construct(private readonly SincronizarItensAutomaticos $sincronizar)
    {
    }

    /** @return Collection<int, AdmissaoChecklist> */
    public function executar(Colaborador $colaborador): Collection
    {
        return DB::transaction(function () use ($colaborador) {
            $colaborador = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            if ($colaborador->status !== StatusColaborador::PreAdmissao) {
                throw ValidationException::withMessages(['colaborador' => 'Só colaborador em pré-admissão inicia a admissão.']);
            }

            $itens = AdmissaoChecklistItem::query()->ativos()->orderBy('ordem')->orderBy('id')->get();
            if ($itens->isEmpty()) {
                throw ValidationException::withMessages(['colaborador' => 'Não há itens ativos no checklist de admissão. Configure os itens primeiro.']);
            }

            $existentes = AdmissaoChecklist::query()->where('colaborador_id', $colaborador->id)->pluck('admissao_checklist_item_id')->filter();
            $novos = $itens->reject(fn (AdmissaoChecklistItem $i) => $existentes->contains($i->id));

            foreach ($novos as $item) {
                AdmissaoChecklist::create([
                    'colaborador_id' => $colaborador->id,
                    'admissao_checklist_item_id' => $item->id,
                    'item' => $item->nome,
                    'chave' => $item->chave,
                    'obrigatorio' => $item->obrigatorio,
                    'ordem' => $item->ordem,
                ]);
            }

            if ($novos->isNotEmpty()) {
                Auditoria::registrar('admissao-iniciada', $colaborador, ['itens' => $novos->count()]);
            }

            return $this->sincronizar->executar($colaborador);
        });
    }
}

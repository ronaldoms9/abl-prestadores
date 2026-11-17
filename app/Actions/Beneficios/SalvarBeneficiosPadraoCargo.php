<?php

namespace App\Actions\Beneficios;

use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\CargoBeneficio;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;

/** Define quais benefícios o cargo dá por padrão na admissão. Troca o conjunto inteiro. */
class SalvarBeneficiosPadraoCargo
{
    /** @param  array<int, int|string>  $beneficioIds */
    public function executar(Cargo $cargo, array $beneficioIds): void
    {
        $ids = Beneficio::query()->whereIn('id', array_map('intval', $beneficioIds))->pluck('id')->all();

        DB::transaction(function () use ($cargo, $ids) {
            $atuais = CargoBeneficio::query()->where('cargo_id', $cargo->id)->pluck('beneficio_id')->all();
            $incluidos = array_values(array_diff($ids, $atuais));
            $removidos = array_values(array_diff($atuais, $ids));

            CargoBeneficio::query()->where('cargo_id', $cargo->id)->whereIn('beneficio_id', $removidos)->get()->each->delete();
            foreach ($incluidos as $id) {
                $linha = CargoBeneficio::withTrashed()->firstOrCreate(['cargo_id' => $cargo->id, 'beneficio_id' => $id]);
                if ($linha->trashed()) {
                    $linha->restore();
                }
            }

            if ($incluidos !== [] || $removidos !== []) {
                Auditoria::registrar('cargo-alterado', $cargo, ['beneficios_padrao_incluidos' => $incluidos, 'beneficios_padrao_removidos' => $removidos]);
            }
        });
    }
}

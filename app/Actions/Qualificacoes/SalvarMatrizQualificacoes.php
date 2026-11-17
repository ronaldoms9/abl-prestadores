<?php

namespace App\Actions\Qualificacoes;

use App\Models\Cargo;
use App\Models\CargoQualificacao;
use App\Models\QualificacaoTipo;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;

/**
 * Grava a matriz cargo × qualificação enviada pela grade. Cada célula é
 * "nenhuma" (sem linha), "opcional" ou "obrigatoria". Só mexe nas células
 * enviadas e só se mudaram; cargos e tipos que não existem são ignorados.
 */
class SalvarMatrizQualificacoes
{
    public const NENHUMA = 'nenhuma';

    public const OPCIONAL = 'opcional';

    public const OBRIGATORIA = 'obrigatoria';

    /**
     * @param  array<int|string, array<int|string, string>>  $celulas  [cargo_id => [tipo_id => estado]]
     * @return int Quantas células mudaram.
     */
    public function executar(array $celulas): int
    {
        $cargos = Cargo::pluck('id')->flip();
        $tipos = QualificacaoTipo::pluck('id')->flip();
        $mudancas = 0;

        DB::transaction(function () use ($celulas, $cargos, $tipos, &$mudancas) {
            $existentes = CargoQualificacao::withTrashed()->get()->keyBy(fn ($l) => $l->cargo_id.':'.$l->qualificacao_tipo_id);

            foreach ($celulas as $cargoId => $linha) {
                foreach ($linha as $tipoId => $estado) {
                    if (! $cargos->has((int) $cargoId) || ! $tipos->has((int) $tipoId)) {
                        continue;
                    }

                    $atual = $existentes->get($cargoId.':'.$tipoId);

                    if ($estado === self::NENHUMA) {
                        if ($atual && ! $atual->trashed()) {
                            $atual->delete();
                            $mudancas++;
                        }

                        continue;
                    }

                    $obrigatoria = $estado === self::OBRIGATORIA;

                    if ($atual === null) {
                        CargoQualificacao::create(['cargo_id' => (int) $cargoId, 'qualificacao_tipo_id' => (int) $tipoId, 'obrigatoria' => $obrigatoria]);
                        $mudancas++;
                    } elseif ($atual->trashed()) {
                        // A linha foi retirada antes: volta em vez de duplicar (par único cargo × tipo).
                        $atual->restore();
                        $atual->update(['obrigatoria' => $obrigatoria]);
                        $mudancas++;
                    } elseif ($atual->obrigatoria !== $obrigatoria) {
                        $atual->update(['obrigatoria' => $obrigatoria]);
                        $mudancas++;
                    }
                }
            }

            if ($mudancas > 0) {
                Auditoria::registrar('matriz-qualificacoes-alterada', null, ['celulas' => $mudancas]);
            }
        });

        return $mudancas;
    }
}

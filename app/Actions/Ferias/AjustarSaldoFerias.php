<?php

namespace App\Actions\Ferias;

use App\Models\FeriasPeriodo;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único ponto que mexe em dias_utilizados. Baixar recusa mais dias do que o
 * saldo (nunca fica negativo); devolver recusa mais dias do que os utilizados.
 * Trava o período para duas aprovações ao mesmo tempo não gastarem o mesmo saldo.
 * Quem chama (aprovação e cancelamento, E4 fatia 3) já checou a competência.
 */
class AjustarSaldoFerias
{
    public function baixar(FeriasPeriodo $periodo, int $dias): FeriasPeriodo
    {
        $this->exigirPositivo($dias);

        return DB::transaction(function () use ($periodo, $dias) {
            $periodo = FeriasPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);

            if ($dias > $periodo->saldo()) {
                throw ValidationException::withMessages(['dias' => "O saldo do período é de {$periodo->saldo()} dia(s); não dá para tirar {$dias}."]);
            }

            $periodo->update(['dias_utilizados' => $periodo->dias_utilizados + $dias]);
            Auditoria::registrar('ferias-saldo-baixado', $periodo->colaborador()->first(), ['periodo' => $periodo->aquisitivo_inicio->toDateString(), 'dias' => $dias]);

            return $periodo;
        });
    }

    public function devolver(FeriasPeriodo $periodo, int $dias): FeriasPeriodo
    {
        $this->exigirPositivo($dias);

        return DB::transaction(function () use ($periodo, $dias) {
            $periodo = FeriasPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);

            if ($dias > $periodo->dias_utilizados) {
                throw ValidationException::withMessages(['dias' => "O período só tem {$periodo->dias_utilizados} dia(s) utilizado(s); não dá para devolver {$dias}."]);
            }

            $periodo->update(['dias_utilizados' => $periodo->dias_utilizados - $dias]);
            Auditoria::registrar('ferias-saldo-devolvido', $periodo->colaborador()->first(), ['periodo' => $periodo->aquisitivo_inicio->toDateString(), 'dias' => $dias]);

            return $periodo;
        });
    }

    private function exigirPositivo(int $dias): void
    {
        if ($dias <= 0) {
            throw ValidationException::withMessages(['dias' => 'Informe um número de dias maior que zero.']);
        }
    }
}

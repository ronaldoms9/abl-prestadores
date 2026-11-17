<?php

namespace App\Actions\Contratos;

use App\Enums\MotivoAditivo;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Contrato;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Último dia de vigência de um contrato, contando as prorrogações. O contrato
 * gerado é imutável, então a data original fica nele e cada aditivo de
 * prorrogação (gerado ou assinado) guarda a nova data em dados.nova_vigencia_fim.
 * O fim do contrato é inclusivo: vale até esse dia.
 */
class VigenciaEfetiva
{
    /** Consulta o contrato inteiro. Quem tem os filhos carregados usa dosFilhos() e poupa uma consulta. */
    public function fim(Contrato $contrato): ?Carbon
    {
        return $this->dosFilhos($contrato, $contrato->filhos()->get());
    }

    /** @param  Collection<int, Contrato>|\Illuminate\Support\Collection<int, Contrato>  $filhos */
    public function dosFilhos(Contrato $contrato, $filhos): ?Carbon
    {
        $fim = $contrato->vigencia_fim;

        foreach ($filhos as $filho) {
            if ($filho->tipo !== TipoContrato::Aditivo || $filho->motivo !== MotivoAditivo::Prorrogacao
                || ! in_array($filho->status, [StatusContrato::Gerado, StatusContrato::Assinado], true)
                || empty($filho->dados['nova_vigencia_fim'])) {
                continue;
            }

            $nova = Carbon::parse($filho->dados['nova_vigencia_fim'])->startOfDay();
            if ($fim === null || $nova->gt($fim)) {
                $fim = $nova;
            }
        }

        return $fim?->copy()->startOfDay();
    }
}

<?php

namespace App\Actions\Beneficios;

use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Define o valor de um benefício a partir de uma data: o primeiro ou uma troca individual. */
class DefinirValorBeneficio
{
    public function __construct(private readonly TrocarValorBeneficio $trocar)
    {
    }

    public function executar(Beneficio $beneficio, string|int|float $valor, CarbonInterface $vigenciaInicio): BeneficioValor
    {
        return DB::transaction(function () use ($beneficio, $valor, $vigenciaInicio) {
            $linha = $this->trocar->executar($beneficio, Dinheiro::centavos($valor), $vigenciaInicio);

            Auditoria::registrar('beneficio-valor-definido', $beneficio, ['valor' => $linha->valor, 'vigencia_inicio' => $linha->vigencia_inicio->toDateString()]);

            return $linha;
        });
    }
}

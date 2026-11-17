<?php

namespace App\Actions\Remuneracoes;

use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Primeira remuneração do colaborador. Depois dela, a mudança é por reajuste. */
class DefinirRemuneracaoInicial
{
    public function __construct(private readonly TrocarLinhaRemuneracao $trocar)
    {
    }

    public function executar(Colaborador $colaborador, string|int|float $valor, CarbonInterface $vigenciaInicio): Remuneracao
    {
        return DB::transaction(function () use ($colaborador, $valor, $vigenciaInicio) {
            if (Remuneracao::where('colaborador_id', $colaborador->id)->exists()) {
                throw ValidationException::withMessages(['valor' => 'O colaborador já tem remuneração. Use o reajuste para alterá-la.']);
            }

            $remuneracao = $this->trocar->executar($colaborador, Dinheiro::centavos($valor), $vigenciaInicio);

            Auditoria::registrar('remuneracao-definida', $colaborador, ['vigencia_inicio' => $remuneracao->vigencia_inicio->toDateString()]);

            return $remuneracao;
        });
    }
}

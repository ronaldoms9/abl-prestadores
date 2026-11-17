<?php

namespace App\Actions\Remuneracoes;

use App\Enums\FormaReajuste;
use App\Enums\TipoReajuste;
use App\Events\ReajusteAplicado;
use App\Models\Colaborador;
use App\Models\Reajuste;
use App\Models\Remuneracao;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reajuste individual: acréscimo percentual ou em reais sobre a remuneração vigente. */
class ReajustarRemuneracao
{
    public function __construct(private readonly TrocarLinhaRemuneracao $trocar)
    {
    }

    public function executar(Colaborador $colaborador, FormaReajuste $forma, string|int|float $valor, CarbonInterface $vigenciaInicio, ?User $aprovador = null): Reajuste
    {
        if (Dinheiro::centavos($valor) <= 0) {
            throw ValidationException::withMessages(['valor' => 'Informe um valor de reajuste maior que zero.']);
        }

        return DB::transaction(function () use ($colaborador, $forma, $valor, $vigenciaInicio, $aprovador) {
            $atual = Remuneracao::query()->where('colaborador_id', $colaborador->id)->vigentes()->lockForUpdate()->first();

            if ($atual === null) {
                throw ValidationException::withMessages(['valor' => 'O colaborador não tem remuneração vigente. Defina a remuneração inicial primeiro.']);
            }

            $novo = Dinheiro::reajustar(Dinheiro::centavos($atual->valor_mensal), $forma, $valor);

            $reajuste = Reajuste::create([
                'tipo' => TipoReajuste::Individual,
                'criterio' => ['colaborador_id' => $colaborador->id],
                'forma' => $forma,
                'valor' => Dinheiro::decimal(Dinheiro::centavos($valor)),
                'vigencia_inicio' => $vigenciaInicio->copy()->startOfDay(),
                'aprovado_por' => $aprovador?->id,
                'aprovado_em' => $aprovador ? now() : null,
            ]);

            $linha = $this->trocar->executar($colaborador, $novo, $vigenciaInicio, $reajuste->id);

            Auditoria::registrar('reajuste-aplicado', $reajuste, ['tipo' => 'individual', 'forma' => $forma->value, 'valor' => $reajuste->valor, 'pessoas' => 1]);

            DB::afterCommit(fn () => ReajusteAplicado::dispatch($reajuste, collect([$linha])));

            return $reajuste;
        });
    }
}

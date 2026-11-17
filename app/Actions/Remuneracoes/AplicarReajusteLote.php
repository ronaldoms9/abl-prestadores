<?php

namespace App\Actions\Remuneracoes;

use App\Enums\FormaReajuste;
use App\Enums\TipoReajuste;
use App\Events\ReajusteAplicado;
use App\Models\Colaborador;
use App\Models\Reajuste;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma o reajuste em lote. Recalcula a prévia dentro da transação e só
 * grava se for igual à que a pessoa viu (hash) e sem linhas com erro. Tudo ou
 * nada: um erro em qualquer colaborador desfaz o lote inteiro.
 */
class AplicarReajusteLote
{
    public function __construct(
        private readonly PreverReajusteLote $prever,
        private readonly TrocarLinhaRemuneracao $trocar,
    ) {
    }

    /**
     * @param  array<string, mixed>  $criterio
     */
    public function executar(array $criterio, FormaReajuste $forma, string|int|float $valor, CarbonInterface $vigenciaInicio, string $hashConfirmado, User $aprovador): Reajuste
    {
        return DB::transaction(function () use ($criterio, $forma, $valor, $vigenciaInicio, $hashConfirmado, $aprovador) {
            $previa = $this->prever->executar($criterio, $forma, $valor, $vigenciaInicio);

            if (! hash_equals($previa['hash'], $hashConfirmado)) {
                throw ValidationException::withMessages(['previa' => 'Os valores mudaram desde a prévia. Gere a prévia de novo e confirme.']);
            }

            if ($previa['erros'] > 0) {
                throw ValidationException::withMessages(['previa' => 'Há colaboradores com erro na prévia. Corrija o critério ou a vigência e gere a prévia de novo.']);
            }

            $reajuste = Reajuste::create([
                'tipo' => TipoReajuste::Lote,
                'criterio' => $criterio,
                'forma' => $forma,
                'valor' => Dinheiro::decimal(Dinheiro::centavos($valor)),
                'vigencia_inicio' => $vigenciaInicio->copy()->startOfDay(),
                'aprovado_por' => $aprovador->id,
                'aprovado_em' => now(),
            ]);

            $colaboradores = Colaborador::query()->whereIn('id', array_column($previa['linhas'], 'colaborador_id'))->get()->keyBy('id');
            $novas = collect($previa['linhas'])->map(
                fn ($linha) => $this->trocar->executar($colaboradores[$linha['colaborador_id']], $linha['novo'], $vigenciaInicio, $reajuste->id)
            );

            Auditoria::registrar('reajuste-aplicado', $reajuste, ['tipo' => 'lote', 'forma' => $forma->value, 'valor' => $reajuste->valor, 'pessoas' => $novas->count()]);

            DB::afterCommit(fn () => ReajusteAplicado::dispatch($reajuste, $novas));

            return $reajuste;
        });
    }
}

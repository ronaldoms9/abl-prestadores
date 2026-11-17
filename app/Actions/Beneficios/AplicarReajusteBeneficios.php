<?php

namespace App\Actions\Beneficios;

use App\Enums\FormaReajuste;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma o reajuste em lote de benefícios. Recalcula a prévia dentro da
 * transação e só grava se for igual à que a pessoa viu (hash) e sem linhas
 * com erro. Tudo ou nada.
 */
class AplicarReajusteBeneficios
{
    public function __construct(
        private readonly PreverReajusteBeneficios $prever,
        private readonly TrocarValorBeneficio $trocar,
    ) {
    }

    /**
     * @param  array<int, int>  $beneficioIds
     * @return Collection<int, BeneficioValor> As linhas novas.
     */
    public function executar(array $beneficioIds, FormaReajuste $forma, string|int|float $valor, CarbonInterface $vigenciaInicio, string $hashConfirmado): Collection
    {
        return DB::transaction(function () use ($beneficioIds, $forma, $valor, $vigenciaInicio, $hashConfirmado) {
            $previa = $this->prever->executar($beneficioIds, $forma, $valor, $vigenciaInicio);

            if (! hash_equals($previa['hash'], $hashConfirmado)) {
                throw ValidationException::withMessages(['previa' => 'Os valores mudaram desde a prévia. Gere a prévia de novo e confirme.']);
            }

            if ($previa['erros'] > 0) {
                throw ValidationException::withMessages(['previa' => 'Há benefícios com erro na prévia. Corrija a escolha ou a vigência e gere a prévia de novo.']);
            }

            $beneficios = Beneficio::query()->whereIn('id', array_column($previa['linhas'], 'beneficio_id'))->get()->keyBy('id');

            $novas = collect($previa['linhas'])->map(
                fn ($linha) => $this->trocar->executar($beneficios[$linha['beneficio_id']], $linha['novo'], $vigenciaInicio)
            );

            Auditoria::registrar('beneficio-reajuste-aplicado', null, [
                'forma' => $forma->value,
                'valor' => Dinheiro::decimal(Dinheiro::centavos($valor)),
                'vigencia_inicio' => $vigenciaInicio->toDateString(),
                'beneficios' => $novas->pluck('beneficio_id')->all(),
            ]);

            return $novas;
        });
    }
}

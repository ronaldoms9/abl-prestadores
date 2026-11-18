<?php

namespace Database\Factories;

use App\Enums\StatusFechamento;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Fechamento;
use Illuminate\Database\Eloquent\Factories\Factory;

class FechamentoFactory extends Factory
{
    protected $model = Fechamento::class;

    /** Fechamento nasce com o rateio de 100% na obra do colaborador, como o cálculo grava (E4, fatia 10). */
    public function configure(): static
    {
        return $this->afterCreating(function (Fechamento $f) {
            if ($f->rateios()->exists()) {
                return;
            }

            $f->rateios()->create([
                'obra_id' => Colaborador::query()->whereKey($f->colaborador_id)->value('obra_id'),
                'percentual' => 100,
                'valor_remuneracao' => $f->valor_remuneracao,
                'total_beneficios' => $f->total_beneficios,
                'total_ajustes' => $f->total_ajustes,
                'total_liquido' => $f->total_liquido,
            ]);
        });
    }

    public function definition(): array
    {
        return [
            'competencia_id' => Competencia::factory(),
            'colaborador_id' => Colaborador::factory(),
            'dias_periodo' => 30,
            'dias_trabalhados' => 22,
            'valor_remuneracao' => 5000,
            'total_beneficios' => 300,
            'total_ajustes' => 0,
            'total_liquido' => 5300,
            'status' => StatusFechamento::Calculado,
            'snapshot' => null,
        ];
    }
}

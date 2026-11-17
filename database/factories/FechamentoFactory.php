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

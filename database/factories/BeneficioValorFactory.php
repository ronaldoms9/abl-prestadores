<?php

namespace Database\Factories;

use App\Models\Beneficio;
use App\Models\BeneficioValor;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficioValorFactory extends Factory
{
    protected $model = BeneficioValor::class;

    public function definition(): array
    {
        return [
            'beneficio_id' => Beneficio::factory(),
            'valor' => 500,
            'vigencia_inicio' => now()->startOfMonth()->toDateString(),
            'vigencia_fim' => null,
        ];
    }
}

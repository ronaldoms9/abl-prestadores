<?php

namespace Database\Factories;

use App\Models\Colaborador;
use App\Models\Remuneracao;
use Illuminate\Database\Eloquent\Factories\Factory;

class RemuneracaoFactory extends Factory
{
    protected $model = Remuneracao::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'valor_mensal' => 5000,
            'vigencia_inicio' => now()->startOfMonth()->toDateString(),
            'vigencia_fim' => null,
            'reajuste_id' => null,
        ];
    }
}

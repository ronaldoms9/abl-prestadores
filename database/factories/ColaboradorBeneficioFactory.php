<?php

namespace Database\Factories;

use App\Models\Beneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use Illuminate\Database\Eloquent\Factories\Factory;

class ColaboradorBeneficioFactory extends Factory
{
    protected $model = ColaboradorBeneficio::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'beneficio_id' => Beneficio::factory(),
            'valor_personalizado' => null,
            'inicio' => now()->startOfMonth()->toDateString(),
            'fim' => null,
        ];
    }
}

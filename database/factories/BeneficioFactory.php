<?php

namespace Database\Factories;

use App\Enums\TipoCalculoBeneficio;
use App\Models\Beneficio;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficioFactory extends Factory
{
    protected $model = Beneficio::class;

    public function definition(): array
    {
        return [
            'nome' => 'Benefício '.fake()->unique()->word(),
            'tipo_calculo' => TipoCalculoBeneficio::FixoMensal,
            'ativo' => true,
        ];
    }

    public function porDia(): static
    {
        return $this->state(['tipo_calculo' => TipoCalculoBeneficio::PorDiaTrabalhado]);
    }
}

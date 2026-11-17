<?php

namespace Database\Factories;

use App\Models\Beneficio;
use App\Models\Cargo;
use App\Models\CargoBeneficio;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargoBeneficioFactory extends Factory
{
    protected $model = CargoBeneficio::class;

    public function definition(): array
    {
        return [
            'cargo_id' => Cargo::factory(),
            'beneficio_id' => Beneficio::factory(),
        ];
    }
}

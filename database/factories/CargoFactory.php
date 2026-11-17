<?php

namespace Database\Factories;

use App\Models\Cargo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargoFactory extends Factory
{
    protected $model = Cargo::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->jobTitle(),
            'descricao' => null,
            'faixa_min' => 3000,
            'faixa_max' => 6000,
            'ativo' => true,
        ];
    }
}

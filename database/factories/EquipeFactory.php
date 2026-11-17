<?php

namespace Database\Factories;

use App\Models\Equipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipeFactory extends Factory
{
    protected $model = Equipe::class;

    public function definition(): array
    {
        return [
            'nome' => 'Equipe '.fake()->unique()->word(),
            'descricao' => null,
            'ativo' => true,
        ];
    }
}

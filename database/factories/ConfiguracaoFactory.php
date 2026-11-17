<?php

namespace Database\Factories;

use App\Models\Configuracao;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfiguracaoFactory extends Factory
{
    protected $model = Configuracao::class;

    public function definition(): array
    {
        return [
            'chave' => fake()->unique()->slug(2),
            'valor' => ['dias' => 30],
        ];
    }
}

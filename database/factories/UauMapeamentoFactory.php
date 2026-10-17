<?php

namespace Database\Factories;

use App\Models\UauMapeamento;
use Illuminate\Database\Eloquent\Factories\Factory;

class UauMapeamentoFactory extends Factory
{
    protected $model = UauMapeamento::class;

    public function definition(): array
    {
        return [
            'tipo' => 'empresa',
            'chave_local' => (string) fake()->unique()->numberBetween(1, 99999),
            'codigo_uau' => (string) fake()->numberBetween(1, 99999),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\StatusCompetencia;
use App\Models\Competencia;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetenciaFactory extends Factory
{
    protected $model = Competencia::class;

    public function definition(): array
    {
        return [
            'ano_mes' => fake()->unique()->date('Y-m'),
            'status' => StatusCompetencia::Aberta,
            'aprovada_por' => null,
            'aprovada_em' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Colaborador;
use App\Models\Equipe;
use App\Models\ColaboradorEquipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class ColaboradorEquipeFactory extends Factory
{
    protected $model = ColaboradorEquipe::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'equipe_id' => Equipe::factory(),
            'inicio' => now()->toDateString(),
            'fim' => null,
        ];
    }
}

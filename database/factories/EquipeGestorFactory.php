<?php

namespace Database\Factories;

use App\Models\Equipe;
use App\Models\User;
use App\Models\EquipeGestor;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipeGestorFactory extends Factory
{
    protected $model = EquipeGestor::class;

    public function definition(): array
    {
        return [
            'equipe_id' => Equipe::factory(),
            'user_id' => User::factory(),
            'inicio' => now()->toDateString(),
            'fim' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Colaborador;
use App\Models\FeriasPeriodo;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriasPeriodoFactory extends Factory
{
    protected $model = FeriasPeriodo::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'aquisitivo_inicio' => '2025-03-02',
            'aquisitivo_fim' => '2026-03-01',
            'dias_direito' => 30,
            'dias_utilizados' => 0,
            'limite_gozo' => '2027-03-01',
        ];
    }
}

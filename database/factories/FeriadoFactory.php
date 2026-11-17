<?php

namespace Database\Factories;

use App\Enums\AbrangenciaFeriado;
use App\Models\Feriado;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriadoFactory extends Factory
{
    protected $model = Feriado::class;

    public function definition(): array
    {
        return [
            'data' => '2026-09-07',
            'descricao' => 'Independência do Brasil',
            'abrangencia' => AbrangenciaFeriado::Nacional,
            'obra_id' => null,
        ];
    }
}

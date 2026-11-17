<?php

namespace Database\Factories;

use App\Models\Cargo;
use App\Models\QualificacaoTipo;
use App\Models\CargoQualificacao;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargoQualificacaoFactory extends Factory
{
    protected $model = CargoQualificacao::class;

    public function definition(): array
    {
        return [
            'cargo_id' => Cargo::factory(),
            'qualificacao_tipo_id' => QualificacaoTipo::factory(),
            'obrigatoria' => true,
        ];
    }
}

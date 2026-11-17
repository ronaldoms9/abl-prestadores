<?php

namespace Database\Factories;

use App\Enums\TipoOcorrencia;
use App\Models\Colaborador;
use App\Models\Ocorrencia;
use Illuminate\Database\Eloquent\Factories\Factory;

class OcorrenciaFactory extends Factory
{
    protected $model = Ocorrencia::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'tipo' => TipoOcorrencia::Falta,
            'inicio' => '2026-07-06',
            'fim' => '2026-07-06',
            'abona' => false,
            'observacao' => null,
        ];
    }
}

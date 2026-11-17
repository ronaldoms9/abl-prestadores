<?php

namespace Database\Factories;

use App\Enums\TipoAjuste;
use App\Models\AjusteAvulso;
use App\Models\Colaborador;
use App\Models\Competencia;
use Illuminate\Database\Eloquent\Factories\Factory;

class AjusteAvulsoFactory extends Factory
{
    protected $model = AjusteAvulso::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'competencia_id' => Competencia::factory(),
            'tipo' => TipoAjuste::Credito,
            'valor' => 100,
            'justificativa' => 'Ajuste de teste',
            'aprovado_por' => null,
        ];
    }
}

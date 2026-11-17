<?php

namespace Database\Factories;

use App\Models\AdmissaoChecklist;
use App\Models\Colaborador;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdmissaoChecklistFactory extends Factory
{
    protected $model = AdmissaoChecklist::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'admissao_checklist_item_id' => null,
            'item' => 'Item '.fake()->unique()->word(),
            'chave' => null,
            'obrigatorio' => true,
            'ordem' => 0,
            'concluido' => false,
        ];
    }
}

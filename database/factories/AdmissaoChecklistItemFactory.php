<?php

namespace Database\Factories;

use App\Models\AdmissaoChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdmissaoChecklistItemFactory extends Factory
{
    protected $model = AdmissaoChecklistItem::class;

    public function definition(): array
    {
        return [
            'nome' => 'Item '.fake()->unique()->word(),
            'chave' => null,
            'obrigatorio' => true,
            'ordem' => 0,
            'ativo' => true,
        ];
    }
}

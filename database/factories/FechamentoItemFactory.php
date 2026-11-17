<?php

namespace Database\Factories;

use App\Enums\TipoItemFechamento;
use App\Models\Fechamento;
use App\Models\FechamentoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class FechamentoItemFactory extends Factory
{
    protected $model = FechamentoItem::class;

    public function definition(): array
    {
        return [
            'fechamento_id' => Fechamento::factory(),
            'tipo' => TipoItemFechamento::Remuneracao,
            'descricao' => 'Remuneração do mês',
            'quantidade' => 1,
            'valor_unitario' => 5000,
            'valor_total' => 5000,
        ];
    }
}

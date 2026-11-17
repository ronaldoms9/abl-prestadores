<?php

namespace Database\Factories;

use App\Enums\FormaReajuste;
use App\Enums\TipoReajuste;
use App\Models\Reajuste;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReajusteFactory extends Factory
{
    protected $model = Reajuste::class;

    public function definition(): array
    {
        return [
            'tipo' => TipoReajuste::Individual,
            'criterio' => null,
            'forma' => FormaReajuste::Percentual,
            'valor' => 5,
            'vigencia_inicio' => now()->toDateString(),
            'aprovado_por' => null,
            'aprovado_em' => null,
        ];
    }
}

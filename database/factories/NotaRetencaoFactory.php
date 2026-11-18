<?php

namespace Database\Factories;

use App\Enums\TributoNota;
use App\Models\NotaFiscal;
use App\Models\NotaRetencao;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaRetencaoFactory extends Factory
{
    protected $model = NotaRetencao::class;

    public function definition(): array
    {
        return ['nota_fiscal_id' => NotaFiscal::factory(), 'tributo' => TributoNota::Iss, 'valor' => 100];
    }
}

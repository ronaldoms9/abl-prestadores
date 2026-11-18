<?php

namespace Database\Factories;

use App\Enums\StatusTituloPagar;
use App\Models\NotaFiscal;
use App\Models\TituloPagar;
use Illuminate\Database\Eloquent\Factories\Factory;

class TituloPagarFactory extends Factory
{
    protected $model = TituloPagar::class;

    public function definition(): array
    {
        return [
            'nota_fiscal_id' => NotaFiscal::factory(),
            'fechamento_id' => fn (array $a) => NotaFiscal::find($a['nota_fiscal_id'])->fechamento_id,
            'valor' => 5300,
            'vencimento' => '2026-11-10',
            'uau_processo' => null,
            'status' => StatusTituloPagar::Pendente,
            'chave_idempotencia' => fn () => 'titulo:'.fake()->unique()->uuid(),
        ];
    }
}

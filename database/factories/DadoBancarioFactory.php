<?php

namespace Database\Factories;

use App\Enums\TipoContaBancaria;
use App\Models\PessoaFisica;
use App\Models\DadoBancario;
use Illuminate\Database\Eloquent\Factories\Factory;

class DadoBancarioFactory extends Factory
{
    protected $model = DadoBancario::class;

    public function definition(): array
    {
        return [
            'pessoa_type' => 'pessoa-fisica',
            'pessoa_id' => PessoaFisica::factory(),
            'banco' => '001 - Banco do Brasil',
            'agencia' => '1234',
            'conta' => '56789-0',
            'tipo' => TipoContaBancaria::Corrente,
            'tipo_chave_pix' => null,
            'chave_pix' => null,
            'principal' => false,
        ];
    }
}

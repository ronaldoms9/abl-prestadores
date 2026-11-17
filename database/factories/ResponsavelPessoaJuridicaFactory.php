<?php

namespace Database\Factories;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\ResponsavelPessoaJuridica;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResponsavelPessoaJuridicaFactory extends Factory
{
    protected $model = ResponsavelPessoaJuridica::class;

    public function definition(): array
    {
        return [
            'pessoa_juridica_id' => PessoaJuridica::factory(),
            'pessoa_fisica_id' => PessoaFisica::factory(),
            'inicio' => now()->toDateString(),
            'fim' => null,
        ];
    }
}

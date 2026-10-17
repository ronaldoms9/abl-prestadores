<?php

namespace Database\Factories;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\VinculoSugerido;
use Illuminate\Database\Eloquent\Factories\Factory;

class VinculoSugeridoFactory extends Factory
{
    protected $model = VinculoSugerido::class;

    public function definition(): array
    {
        return [
            'pessoa_fisica_id' => PessoaFisica::factory(),
            'pessoa_juridica_id' => PessoaJuridica::factory(),
            'importacao_pessoa_id' => null,
            'utilizado_em' => null,
        ];
    }
}

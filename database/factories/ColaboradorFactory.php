<?php

namespace Database\Factories;

use App\Enums\StatusColaborador;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\Colaborador;
use Illuminate\Database\Eloquent\Factories\Factory;

class ColaboradorFactory extends Factory
{
    protected $model = Colaborador::class;

    public function definition(): array
    {
        return [
            'pessoa_fisica_id' => PessoaFisica::factory(),
            'pessoa_juridica_id' => PessoaJuridica::factory(),
            'cargo_id' => null,
            'empresa_id' => null,
            'obra_id' => null,
            'status' => StatusColaborador::PreAdmissao,
            'data_admissao' => null,
            'data_desligamento' => null,
            'motivo_desligamento' => null,
        ];
    }
}

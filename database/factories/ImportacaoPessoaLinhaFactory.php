<?php

namespace Database\Factories;

use App\Enums\ResultadoImportacao;
use App\Models\ImportacaoPessoa;
use App\Models\ImportacaoPessoaLinha;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportacaoPessoaLinhaFactory extends Factory
{
    protected $model = ImportacaoPessoaLinha::class;

    public function definition(): array
    {
        return [
            'importacao_pessoa_id' => ImportacaoPessoa::factory(),
            'linha' => fake()->unique()->numberBetween(1, 99999),
            'cpf' => fake('pt_BR')->cpf(false),
            'cnpj' => fake('pt_BR')->cnpj(false),
            'resultado_pf' => ResultadoImportacao::Importada,
            'resultado_pj' => ResultadoImportacao::Importada,
            'mensagem' => null,
        ];
    }
}

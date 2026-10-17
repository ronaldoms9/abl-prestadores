<?php

namespace Database\Factories;

use App\Models\PessoaJuridica;
use App\Support\Endereco;
use Illuminate\Database\Eloquent\Factories\Factory;

class PessoaJuridicaFactory extends Factory
{
    protected $model = PessoaJuridica::class;

    public function definition(): array
    {
        return [
            'uau_codigo' => fake()->unique()->numberBetween(1, 99999),
            'cnpj' => fake('pt_BR')->unique()->cnpj(false),
            'razao_social' => fake()->company(),
            'nome_fantasia' => fake()->companySuffix(),
            'regime_tributario' => null,
            'inscricao_municipal' => fake()->numerify('########'),
            'inscricao_estadual' => fake()->numerify('#########'),
            'email' => fake()->safeEmail(),
            'telefone' => fake()->numerify('623#######'),
            'endereco' => new Endereco(
                logradouro: fake()->streetName(),
                numero: (string) fake()->buildingNumber(),
                complemento: null,
                bairro: 'Setor Marista',
                cidade: 'Goiânia',
                uf: 'GO',
                cep: '74150000',
            ),
            'ativo' => true,
            'sincronizado_em' => null,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function semCodigoUau(): static
    {
        return $this->state(fn () => ['uau_codigo' => null]);
    }

    public function sincronizado(): static
    {
        return $this->state(fn () => ['sincronizado_em' => now()]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Support\Endereco;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'uau_codigo' => fake()->unique()->numberBetween(1, 99999),
            'cnpj' => fake('pt_BR')->unique()->cnpj(false),
            'razao_social' => fake()->company(),
            'inscricao_estadual' => fake()->numerify('#########'),
            'inscricao_municipal' => fake()->numerify('########'),
            'endereco' => new Endereco(
                logradouro: fake()->streetName(),
                numero: (string) fake()->buildingNumber(),
                complemento: 'Sala 101',
                bairro: 'Centro',
                cidade: 'Goiânia',
                uf: 'GO',
                cep: '74000000',
            ),
            'telefone' => fake()->numerify('629########'),
            'ativo' => true,
            'sincronizado_em' => null,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }

    public function sincronizado(): static
    {
        return $this->state(fn () => ['sincronizado_em' => now()]);
    }
}

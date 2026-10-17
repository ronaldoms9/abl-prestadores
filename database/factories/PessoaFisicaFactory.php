<?php

namespace Database\Factories;

use App\Models\PessoaFisica;
use App\Support\Endereco;
use Illuminate\Database\Eloquent\Factories\Factory;

class PessoaFisicaFactory extends Factory
{
    protected $model = PessoaFisica::class;

    public function definition(): array
    {
        return [
            'uau_codigo' => fake()->unique()->numberBetween(1, 99999),
            'cpf' => fake('pt_BR')->cpf(false),
            'nome' => fake()->name(),
            'nascimento' => fake()->date('Y-m-d', '-18 years'),
            'email' => fake()->safeEmail(),
            'telefone' => fake()->numerify('629########'),
            'endereco' => new Endereco(
                logradouro: fake()->streetName(),
                numero: (string) fake()->buildingNumber(),
                complemento: 'Apto 101',
                bairro: 'Jardim Goiás',
                cidade: 'Goiânia',
                uf: 'GO',
                cep: '74805000',
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

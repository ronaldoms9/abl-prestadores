<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Obra;
use App\Support\Endereco;
use Illuminate\Database\Eloquent\Factories\Factory;

class ObraFactory extends Factory
{
    protected $model = Obra::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'uau_codigo' => (string) fake()->unique()->numberBetween(1, 9999),
            'descricao' => fake()->sentence(3),
            'status_uau' => 1,
            'tipo_uau' => 'OBR',
            'endereco' => new Endereco(
                logradouro: fake()->streetName(),
                numero: (string) fake()->buildingNumber(),
                complemento: null,
                bairro: 'Setor Bueno',
                cidade: 'Goiânia',
                uf: 'GO',
                cep: '74210000',
            ),
            'data_inicio' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'data_fim' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
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

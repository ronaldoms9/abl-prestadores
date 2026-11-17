<?php

namespace Database\Factories;

use App\Enums\TipoContrato;
use App\Models\ContratoModelo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratoModeloFactory extends Factory
{
    protected $model = ContratoModelo::class;

    public function definition(): array
    {
        return [
            'nome' => 'Contrato '.fake()->unique()->word(),
            'tipo' => TipoContrato::Contrato,
            'motivo' => null,
            'corpo_html' => '<p>Contrato entre {{pj.razao_social}} e {{colaborador.nome}}.</p>',
            'versao' => 1,
            'modelo_anterior_id' => null,
            'ativo' => true,
        ];
    }

    public function tipo(TipoContrato $tipo): static
    {
        return $this->state(['tipo' => $tipo]);
    }
}

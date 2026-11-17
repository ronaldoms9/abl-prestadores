<?php

namespace Database\Factories;

use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'contrato_modelo_id' => ContratoModelo::factory(),
            'contrato_pai_id' => null,
            'tipo' => TipoContrato::Contrato,
            'motivo' => null,
            'numero' => null,
            'ano' => null,
            'sequencia' => null,
            'conteudo_renderizado' => null,
            'dados' => null,
            'vigencia_inicio' => now()->startOfMonth()->toDateString(),
            'vigencia_fim' => null,
            'status' => StatusContrato::Rascunho,
        ];
    }

    public function gerado(string $numero = '2026/0001'): static
    {
        return $this->state(fn () => [
            'numero' => $numero,
            'ano' => (int) substr($numero, 0, 4),
            'sequencia' => (int) substr($numero, 5, 4),
            'conteudo_renderizado' => '<p>Contrato gerado.</p>',
            'status' => StatusContrato::Gerado,
            'gerado_em' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\StatusSolicitacaoFerias;
use App\Models\Colaborador;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriasSolicitacaoFactory extends Factory
{
    protected $model = FeriasSolicitacao::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'ferias_periodo_id' => FeriasPeriodo::factory(),
            'inicio' => '2026-07-01',
            'fim' => '2026-07-10',
            'dias' => 10,
            'status' => StatusSolicitacaoFerias::Pendente,
            'solicitado_por' => null,
            'nivel_atual' => 1,
            'observacao' => null,
        ];
    }
}

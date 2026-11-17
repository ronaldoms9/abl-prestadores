<?php

namespace Database\Factories;

use App\Enums\DecisaoFerias;
use App\Models\FeriasAprovacao;
use App\Models\FeriasSolicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriasAprovacaoFactory extends Factory
{
    protected $model = FeriasAprovacao::class;

    public function definition(): array
    {
        return [
            'ferias_solicitacao_id' => FeriasSolicitacao::factory(),
            'nivel' => 1,
            'aprovador_id' => null,
            'decisao' => DecisaoFerias::Aprovada,
            'observacao' => null,
            'decidido_em' => now(),
            'escalada' => false,
            'motivo_escalada' => null,
        ];
    }
}

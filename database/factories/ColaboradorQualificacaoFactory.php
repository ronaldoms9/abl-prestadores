<?php

namespace Database\Factories;

use App\Models\Colaborador;
use App\Models\QualificacaoTipo;
use App\Models\ColaboradorQualificacao;
use Illuminate\Database\Eloquent\Factories\Factory;

class ColaboradorQualificacaoFactory extends Factory
{
    protected $model = ColaboradorQualificacao::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => Colaborador::factory(),
            'qualificacao_tipo_id' => QualificacaoTipo::factory(),
            'data_conclusao' => now()->subMonths(2)->toDateString(),
            'data_validade' => now()->addMonths(10)->toDateString(),
            'certificado_path' => null,
            'instituicao' => null,
        ];
    }
}

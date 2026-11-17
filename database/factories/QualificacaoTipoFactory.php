<?php

namespace Database\Factories;

use App\Enums\CategoriaQualificacao;
use App\Models\QualificacaoTipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualificacaoTipoFactory extends Factory
{
    protected $model = QualificacaoTipo::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->words(2, true),
            'categoria' => CategoriaQualificacao::Curso,
            'validade_meses' => 12,
            'ativo' => true,
        ];
    }
}

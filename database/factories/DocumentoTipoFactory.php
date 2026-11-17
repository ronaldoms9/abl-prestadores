<?php

namespace Database\Factories;

use App\Enums\AplicaDocumento;
use App\Models\DocumentoTipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoTipoFactory extends Factory
{
    protected $model = DocumentoTipo::class;

    public function definition(): array
    {
        return [
            'nome' => 'Documento '.fake()->unique()->word(),
            'aplica_a' => AplicaDocumento::Todos,
            'exige_validade' => false,
            'obrigatorio_na_admissao' => false,
            'ativo' => true,
        ];
    }

    public function obrigatorioNaAdmissao(): static
    {
        return $this->state(fn () => ['obrigatorio_na_admissao' => true]);
    }

    public function comValidade(): static
    {
        return $this->state(fn () => ['exige_validade' => true]);
    }
}

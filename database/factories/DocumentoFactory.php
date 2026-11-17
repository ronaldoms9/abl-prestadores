<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\PessoaFisica;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        return [
            'documentavel_type' => 'pessoa-fisica',
            'documentavel_id' => PessoaFisica::factory(),
            'documento_tipo_id' => DocumentoTipo::factory(),
            'tipo' => 'Documento',
            'arquivo_path' => null,
            'validade' => null,
            'observacao' => null,
        ];
    }
}

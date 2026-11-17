<?php

namespace Database\Factories;

use App\Models\PessoaFisica;
use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        return [
            'documentavel_type' => 'pessoa-fisica',
            'documentavel_id' => PessoaFisica::factory(),
            'tipo' => 'RG',
            'arquivo_path' => null,
            'validade' => null,
            'observacao' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\StatusImportacao;
use App\Models\ImportacaoPessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportacaoPessoaFactory extends Factory
{
    protected $model = ImportacaoPessoa::class;

    public function definition(): array
    {
        return [
            'arquivo_path' => 'importacoes/' . fake()->uuid() . '.xlsx',
            'nome_original' => 'prestadores.xlsx',
            'status' => StatusImportacao::Pendente,
            'total_linhas' => 0,
            'resumo' => null,
            'erro' => null,
            'iniciada_em' => null,
            'concluida_em' => null,
        ];
    }

    public function concluida(): static
    {
        return $this->state(fn () => [
            'status' => StatusImportacao::Concluida,
            'total_linhas' => 10,
            'resumo' => ['importadas' => 8, 'ja_existiam' => 2],
            'iniciada_em' => now()->subMinutes(2),
            'concluida_em' => now(),
        ]);
    }

    public function falhou(): static
    {
        return $this->state(fn () => [
            'status' => StatusImportacao::Falhou,
            'erro' => 'Arquivo corrompido ou formato inválido',
            'iniciada_em' => now()->subMinutes(1),
            'concluida_em' => now(),
        ]);
    }
}

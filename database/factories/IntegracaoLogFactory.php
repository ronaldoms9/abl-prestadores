<?php

namespace Database\Factories;

use App\Enums\DirecaoIntegracao;
use App\Enums\StatusIntegracao;
use App\Models\IntegracaoLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class IntegracaoLogFactory extends Factory
{
    protected $model = IntegracaoLog::class;

    public function definition(): array
    {
        return [
            'direcao' => DirecaoIntegracao::Entrada,
            'entidade_type' => null,
            'entidade_id' => null,
            'operacao' => 'ConsultarEmpresas',
            'rota' => '/uauAPI/api/v1.0/Empresas/ObterEmpresasAtivas',
            'chave_idempotencia' => null,
            'payload' => ['pagina' => 1],
            'resposta' => ['itens' => []],
            'http_status' => 200,
            'status' => StatusIntegracao::Sucesso,
            'tentativas' => 1,
            'erro' => null,
            'duracao_ms' => 150,
            'executado_em' => now(),
        ];
    }

    public function sucesso(): static
    {
        return $this->state(fn () => [
            'status' => StatusIntegracao::Sucesso,
            'http_status' => 200,
            'erro' => null,
        ]);
    }

    public function erro(): static
    {
        return $this->state(fn () => [
            'status' => StatusIntegracao::Erro,
            'http_status' => 500,
            'erro' => 'Falha na conexão',
        ]);
    }

    public function paraEntidade(Model $model): static
    {
        return $this->state(fn () => [
            'entidade_type' => $model->getMorphClass(),
            'entidade_id' => $model->getKey(),
        ]);
    }
}

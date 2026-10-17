<?php

namespace Database\Factories;

use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Models\Sincronizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sincronizacao>
 */
class SincronizacaoFactory extends Factory
{
    protected $model = Sincronizacao::class;

    public function definition(): array
    {
        return [
            'origem' => OrigemSincronizacao::Agendada,
            'status' => StatusSincronizacao::Concluida,
            'iniciada_em' => now()->subMinutes(5),
            'concluida_em' => now(),
            'erro' => null,
        ];
    }

    public function emAndamento(): static
    {
        return $this->state(fn () => ['status' => StatusSincronizacao::EmAndamento, 'concluida_em' => null]);
    }
}

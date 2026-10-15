<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $nome = $this->faker->company();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.$this->faker->unique()->numerify('####'),
            'ativo_ate' => today()->addMonth(),
            'waha_instance' => 'waha-1',
            'waha_session' => 'sessao-'.$this->faker->unique()->numerify('#####'),
            'teto_mensagens_mes' => 3000,
            'mensagens_mes' => 0,
        ];
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['ativo_ate' => today()->subDay()]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'chat_id' => $this->faker->unique()->numerify('55119########').'@c.us',
            'estado' => Conversation::ESTADO_BOT,
            'ultima_mensagem_at' => now(),
            'expurgo_em' => now()->addDays(90),
        ];
    }

    public function comHumano(): static
    {
        return $this->state(fn () => [
            'estado' => Conversation::ESTADO_HUMANO,
            'pausada_ate' => now()->addHours(4),
        ]);
    }
}

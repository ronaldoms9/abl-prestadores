<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $conversa = Conversation::factory();

        return [
            'conversation_id' => $conversa,
            'tenant_id' => fn (array $atributos) => Conversation::find($atributos['conversation_id'])?->tenant_id,
            'direcao' => Message::ENTRADA,
            'tipo' => 'texto',
            'conteudo' => $this->faker->sentence(),
        ];
    }

    public function deSaida(): static
    {
        return $this->state(fn () => ['direcao' => Message::SAIDA]);
    }
}

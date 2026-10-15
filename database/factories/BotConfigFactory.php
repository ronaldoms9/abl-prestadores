<?php

namespace Database\Factories;

use App\Models\BotConfig;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BotConfigFactory extends Factory
{
    protected $model = BotConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nome_negocio' => 'Barbearia do Zé',
            'descricao' => 'Barbearia de bairro, atendimento por ordem de chegada.',
            'tom' => 'informal',
            'horarios' => [
                'segunda' => ['abre' => '09:00', 'fecha' => '19:00'],
                'terca' => ['abre' => '09:00', 'fecha' => '19:00'],
                'quarta' => ['abre' => '09:00', 'fecha' => '19:00'],
                'quinta' => ['abre' => '09:00', 'fecha' => '19:00'],
                'sexta' => ['abre' => '09:00', 'fecha' => '19:00'],
                'sabado' => ['abre' => '09:00', 'fecha' => '13:00'],
                'domingo' => 'fechado',
            ],
            'produtos' => [
                ['nome' => 'Corte masculino', 'preco' => 45.00, 'descricao' => 'Tesoura e máquina'],
                ['nome' => 'Barba', 'preco' => 35.00, 'descricao' => null],
                ['nome' => 'Corte + barba', 'preco' => 70.00, 'descricao' => 'Combo'],
            ],
            'faqs' => [
                ['pergunta' => 'Precisa agendar?', 'resposta' => 'Não, é por ordem de chegada.'],
            ],
            'nunca_faca' => 'Nunca prometa desconto.',
            'quando_humano' => 'Quando o cliente reclamar ou pedir para falar com o dono.',
            'saudacao' => 'Fala! Aqui é a Barbearia do Zé.',
        ];
    }
}

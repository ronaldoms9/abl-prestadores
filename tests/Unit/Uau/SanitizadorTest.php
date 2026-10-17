<?php

namespace Tests\Unit\Uau;

use App\Integrations\Uau\Sanitizador;
use PHPUnit\Framework\TestCase;

class SanitizadorTest extends TestCase
{
    public function test_oculta_campos_em_qualquer_nivel_sem_diferenciar_maiusculas(): void
    {
        $sanitizador = new Sanitizador(['senha', 'senhaUauWeb', 'dadosBancarios', 'token']);

        $limpo = $sanitizador->limpar([
            'Login' => 'abl',
            'Senha' => 'segredo',
            'pessoa' => ['nome' => 'Ana', 'SENHAUAUWEB' => 'x', 'dadosBancarios' => [['conta' => '1']]],
            'lista' => [['token' => 'abc'], ['outro' => 'mantido']],
        ]);

        $this->assertSame([
            'Login' => 'abl',
            'Senha' => '[oculto]',
            'pessoa' => ['nome' => 'Ana', 'SENHAUAUWEB' => '[oculto]', 'dadosBancarios' => '[oculto]'],
            'lista' => [['token' => '[oculto]'], ['outro' => 'mantido']],
        ], $limpo);
    }

    public function test_valor_que_nao_e_array_passa_igual(): void
    {
        $this->assertSame('texto', (new Sanitizador(['senha']))->limpar('texto'));
        $this->assertNull((new Sanitizador(['senha']))->limpar(null));
    }
}

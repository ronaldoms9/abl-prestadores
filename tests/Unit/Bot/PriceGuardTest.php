<?php

namespace Tests\Unit\Bot;

use App\Bot\PriceGuard;
use App\Models\BotConfig;
use PHPUnit\Framework\TestCase;

class PriceGuardTest extends TestCase
{
    private function config(): BotConfig
    {
        $config = new BotConfig();
        $config->produtos = [
            ['nome' => 'Corte masculino', 'preco' => 45.00],
            ['nome' => 'Barba', 'preco' => 35.00],
            ['nome' => 'Corte + barba', 'preco' => 70.00],
            ['nome' => 'Pacote mensal', 'preco' => 1250.90],
        ];

        return $config;
    }

    public function test_preco_da_lista_passa(): void
    {
        $veredito = (new PriceGuard())->verificar('O corte sai R$ 45,00 e a barba R$ 35.', $this->config());

        $this->assertTrue($veredito->liberado);
    }

    public function test_preco_inventado_bloqueia(): void
    {
        $veredito = (new PriceGuard())->verificar('Faço por R$ 30,00 pra você.', $this->config());

        $this->assertFalse($veredito->liberado);
        $this->assertSame([30.0], $veredito->inventados);
        $this->assertStringContainsString('R$ 30,00', $veredito->motivo());
    }

    public function test_desconto_calculado_pelo_modelo_bloqueia(): void
    {
        // 10% de desconto sobre 70 é exatamente o tipo de invenção que mata a margem.
        $veredito = (new PriceGuard())->verificar('Combo com desconto: 63 reais.', $this->config());

        $this->assertFalse($veredito->liberado);
    }

    public function test_numero_que_nao_e_dinheiro_nao_bloqueia(): void
    {
        $texto = 'Atendemos sábado das 9h às 13h, somos 3 barbeiros e ficamos no número 250 da rua.';

        $this->assertTrue((new PriceGuard())->verificar($texto, $this->config())->liberado);
    }

    public function test_resposta_sem_preco_passa(): void
    {
        $this->assertTrue((new PriceGuard())->verificar('Pode vir sem agendar!', $this->config())->liberado);
    }

    public function test_entende_formato_brasileiro_com_milhar(): void
    {
        $guarda = new PriceGuard();

        $this->assertSame([1250.90], $guarda->precosCitados('O pacote mensal custa R$ 1.250,90.'));
        $this->assertTrue($guarda->verificar('O pacote mensal custa R$ 1.250,90.', $this->config())->liberado);
    }

    public function test_reconhece_preco_escrito_como_reais(): void
    {
        $this->assertSame([45.0], (new PriceGuard())->precosCitados('O corte custa 45 reais.'));
    }
}

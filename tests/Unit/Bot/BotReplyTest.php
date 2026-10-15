<?php

namespace Tests\Unit\Bot;

use App\Bot\BotReply;
use App\Bot\BotReplyInvalida;
use PHPUnit\Framework\TestCase;

class BotReplyTest extends TestCase
{
    public function test_le_o_json_do_modelo(): void
    {
        $reply = BotReply::deJson('{"resposta":"Atendemos sábado até 13h","confianca":0.91,"precisa_humano":false,"motivo":null}');

        $this->assertSame('Atendemos sábado até 13h', $reply->resposta);
        $this->assertSame(0.91, $reply->confianca);
        $this->assertFalse($reply->precisaHumano);
        $this->assertTrue($reply->confiavel(0.7));
    }

    public function test_aceita_json_embrulhado_em_cerca_de_codigo(): void
    {
        $reply = BotReply::deJson("```json\n{\"resposta\":\"oi\",\"confianca\":0.8,\"precisa_humano\":false}\n```");

        $this->assertSame('oi', $reply->resposta);
    }

    public function test_json_invalido_vira_excecao(): void
    {
        $this->expectException(BotReplyInvalida::class);

        BotReply::deJson('desculpe, não entendi a pergunta');
    }

    public function test_json_sem_campo_resposta_vira_excecao(): void
    {
        $this->expectException(BotReplyInvalida::class);

        BotReply::deJson('{"confianca":0.9}');
    }

    public function test_confianca_baixa_nao_e_confiavel(): void
    {
        $reply = BotReply::deJson('{"resposta":"acho que sim","confianca":0.42,"precisa_humano":false}');

        $this->assertFalse($reply->confiavel(0.7));
    }

    public function test_pedido_explicito_de_humano_nao_e_confiavel(): void
    {
        $reply = BotReply::deJson('{"resposta":"vou chamar alguém","confianca":0.99,"precisa_humano":true,"motivo":"reclamação"}');

        $this->assertFalse($reply->confiavel(0.7));
        $this->assertSame('reclamação', $reply->motivo);
    }
}

<?php

namespace Tests\Feature\Bot;

use App\Bot\PromptBuilder;
use App\Models\BotConfig;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloco_fixo_vem_primeiro_e_traz_a_configuracao_do_negocio(): void
    {
        $config = BotConfig::factory()->create();
        $conversa = Conversation::factory()->create(['tenant_id' => $config->tenant_id]);

        $mensagens = (new PromptBuilder())->build($config, $conversa, 'quanto custa o corte?');

        $this->assertSame('system', $mensagens[0]['role']);
        $this->assertStringContainsString('Barbearia do Zé', $mensagens[0]['content']);
        $this->assertStringContainsString('R$ 45,00', $mensagens[0]['content']);
        $this->assertStringContainsString('Nunca prometa desconto', $mensagens[0]['content']);

        $this->assertSame('user', end($mensagens)['role']);
        $this->assertSame('quanto custa o corte?', end($mensagens)['content']);
    }

    public function test_bloco_fixo_e_identico_entre_chamadas(): void
    {
        // Se algo variável (data, hora, nome do contato) entrar aqui, o cache do
        // provedor nunca acerta e se paga integral em toda mensagem.
        $config = BotConfig::factory()->create();
        $builder = new PromptBuilder();

        $primeiro = $builder->blocoFixo($config);

        $this->travel(3)->hours();

        $this->assertSame($primeiro, $builder->blocoFixo($config->fresh()));
    }

    public function test_historico_vem_depois_do_bloco_fixo_e_em_ordem(): void
    {
        $config = BotConfig::factory()->create();
        $conversa = Conversation::factory()->create(['tenant_id' => $config->tenant_id]);

        Message::factory()->create(['conversation_id' => $conversa->id, 'tenant_id' => $config->tenant_id, 'conteudo' => 'oi']);
        Message::factory()->deSaida()->create(['conversation_id' => $conversa->id, 'tenant_id' => $config->tenant_id, 'conteudo' => 'Fala! Como posso ajudar?']);

        $mensagens = (new PromptBuilder())->build($config, $conversa, 'quanto custa?');

        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($mensagens, 'role'));
        $this->assertSame('oi', $mensagens[1]['content']);
        $this->assertSame('Fala! Como posso ajudar?', $mensagens[2]['content']);
    }

    public function test_historico_respeita_o_limite_de_dez_mensagens(): void
    {
        $config = BotConfig::factory()->create();
        $conversa = Conversation::factory()->create(['tenant_id' => $config->tenant_id]);

        Message::factory()->count(15)->create([
            'conversation_id' => $conversa->id,
            'tenant_id' => $config->tenant_id,
        ]);

        $mensagens = (new PromptBuilder())->build($config, $conversa, 'e agora?');

        // 1 system + 10 de histórico + 1 mensagem atual
        $this->assertCount(12, $mensagens);
    }

    public function test_historico_ignora_mensagens_com_mais_de_24h(): void
    {
        $config = BotConfig::factory()->create();
        $conversa = Conversation::factory()->create(['tenant_id' => $config->tenant_id]);

        $antiga = Message::factory()->create([
            'conversation_id' => $conversa->id,
            'tenant_id' => $config->tenant_id,
            'conteudo' => 'conversa de ontem',
        ]);
        $antiga->forceFill(['created_at' => now()->subHours(30)])->save();

        $mensagens = (new PromptBuilder())->build($config, $conversa, 'oi de novo');

        $this->assertCount(2, $mensagens);
    }

    public function test_usa_transcricao_quando_a_mensagem_foi_audio(): void
    {
        $config = BotConfig::factory()->create();
        $conversa = Conversation::factory()->create(['tenant_id' => $config->tenant_id]);

        Message::factory()->create([
            'conversation_id' => $conversa->id,
            'tenant_id' => $config->tenant_id,
            'tipo' => 'audio',
            'conteudo' => null,
            'transcricao' => 'vocês abrem no domingo?',
        ]);

        $mensagens = (new PromptBuilder())->build($config, $conversa, 'alô?');

        $this->assertSame('vocês abrem no domingo?', $mensagens[1]['content']);
    }
}

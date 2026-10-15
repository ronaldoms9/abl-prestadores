<?php

namespace Tests\Feature\Bot;

use App\Jobs\ProcessIncomingMessage;
use App\Jobs\RespondToConversation;
use App\Models\BotConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetricDaily;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConversaPrecisaDeHumano;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Fake\FakeWhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversaTest extends TestCase
{
    use RefreshDatabase;

    private const CHAVE = 'hmac-de-teste';
    private const CHAT = '5511999999999@c.us';

    private Tenant $tenant;
    private FakeWhatsAppProvider $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['whatsapp.hmac_key' => self::CHAVE]);

        $this->tenant = Tenant::factory()->create(['waha_session' => 'demo']);
        BotConfig::factory()->create(['tenant_id' => $this->tenant->id]);
        User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->whatsapp = app(WhatsAppProvider::class);
        $this->whatsapp->limpar();
    }

    /** Resposta do modelo, já no formato que o BotReply espera. */
    private function fingirLlm(array $reply, array $uso = []): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'google/gemini-2.5-flash-lite',
            'choices' => [['message' => ['content' => json_encode($reply, JSON_UNESCAPED_UNICODE)]]],
            'usage' => array_merge([
                'prompt_tokens' => 820,
                'completion_tokens' => 44,
                'cost' => 0.00012,
            ], $uso),
        ])]);
    }

    private function postarFragmento(string $texto): void
    {
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/waha/mensagem-texto.json')), true);
        $payload['payload']['body'] = $texto;
        $payload['payload']['id'] = 'false_'.self::CHAT.'_'.md5($texto);
        $payload['payload']['timestamp'] = now()->getTimestamp();

        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $servidor = [
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $corpo, self::CHAVE),
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (now()->getTimestamp() * 1000),
            'HTTP_X_WEBHOOK_REQUEST_ID' => 'req-'.md5($corpo),
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call('POST', '/api/webhooks/waha', [], [], [], $servidor, $corpo)->assertOk();
    }

    /**
     * Roda a fila na ordem em que os jobs foram enfileirados, sem sync: é o
     * sync que mascararia o debounce, executando a resposta antes do fragmento
     * seguinte chegar.
     */
    private function rodarFila(): void
    {
        foreach (Queue::pushed(ProcessIncomingMessage::class) as $job) {
            $this->app->call([$job, 'handle']);
        }

        foreach (Queue::pushed(RespondToConversation::class) as $job) {
            $this->app->call([$job, 'handle']);
        }
    }

    public function test_rajada_de_quatro_fragmentos_gera_uma_resposta_so(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'Sim! Sábado das 9h às 13h.', 'confianca' => 0.93, 'precisa_humano' => false]);

        foreach (['oi', 'bom dia', 'queria saber', 'vocês atendem sábado?'] as $fragmento) {
            $this->postarFragmento($fragmento);
        }

        Queue::assertPushed(ProcessIncomingMessage::class, 4);

        $this->rodarFila();

        // Quatro gerações foram agendadas, mas três tiveram o token invalidado.
        Queue::assertPushed(RespondToConversation::class, 4);
        $this->whatsapp->assertSentCount(1, self::CHAT);
        $this->whatsapp->assertSentText(self::CHAT, 'Sábado das 9h às 13h');

        $conversa = Conversation::first();
        $this->assertSame(4, $conversa->messages()->where('direcao', Message::ENTRADA)->count());
        $this->assertSame(1, $conversa->messages()->where('direcao', Message::SAIDA)->count());
    }

    public function test_sequencia_anti_banimento_e_respeitada(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'Pode vir sem agendar!', 'confianca' => 0.95, 'precisa_humano' => false]);

        $this->postarFragmento('precisa agendar?');
        $this->rodarFila();

        $this->assertSame(['seen', 'typing.start', 'typing.stop', 'text'], $this->whatsapp->sequencia(self::CHAT));
    }

    public function test_preco_inventado_e_bloqueado_e_escalado(): void
    {
        Queue::fake();
        Notification::fake();

        // 30 reais não está na lista de produtos do tenant.
        $this->fingirLlm(['resposta' => 'Faço o corte por R$ 30,00 pra você!', 'confianca' => 0.97, 'precisa_humano' => false]);

        $this->postarFragmento('faz por 30?');
        $this->rodarFila();

        $this->whatsapp->assertSentText(self::CHAT, config('whatsapp.mensagem_escape'));
        $this->assertSame(Conversation::ESTADO_HUMANO, Conversation::first()->estado);

        // A resposta barrada não entra no histórico.
        $this->assertSame(0, Message::where('direcao', Message::SAIDA)->whereNotNull('conteudo')->count());

        Notification::assertSentTo($this->tenant->users()->get(), ConversaPrecisaDeHumano::class);
        $this->assertSame(1, MetricDaily::first()->escaladas);
    }

    public function test_confianca_baixa_escala_em_vez_de_responder(): void
    {
        Queue::fake();
        Notification::fake();
        $this->fingirLlm(['resposta' => 'acho que sim', 'confianca' => 0.31, 'precisa_humano' => false]);

        $this->postarFragmento('vocês fazem progressiva?');
        $this->rodarFila();

        $this->whatsapp->assertSentText(self::CHAT, config('whatsapp.mensagem_escape'));
        $this->assertSame(Conversation::ESTADO_HUMANO, Conversation::first()->estado);
    }

    public function test_json_invalido_do_modelo_escala_sem_deixar_o_cliente_no_vazio(): void
    {
        Queue::fake();
        Notification::fake();

        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'google/gemini-2.5-flash-lite',
            'choices' => [['message' => ['content' => 'desculpe, não consigo responder isso']]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 10, 'cost' => 0.00001],
        ])]);

        $this->postarFragmento('tem estacionamento?');
        $this->rodarFila();

        $this->whatsapp->assertSentText(self::CHAT, config('whatsapp.mensagem_escape'));
        $this->assertSame(Conversation::ESTADO_HUMANO, Conversation::first()->estado);
    }

    public function test_llm_fora_do_ar_ainda_responde_ao_cliente_e_avisa_o_dono(): void
    {
        Queue::fake();
        Notification::fake();

        Http::fake(['openrouter.ai/*' => Http::response(['error' => 'indisponível'], 503)]);

        $this->postarFragmento('bom dia');
        $this->rodarFila();

        // Silêncio é a única falha que o dono nunca perdoa.
        $this->whatsapp->assertSentText(self::CHAT, config('whatsapp.mensagem_escape'));
        Notification::assertSentTo($this->tenant->users()->get(), ConversaPrecisaDeHumano::class);
    }

    public function test_conversa_com_humano_nao_recebe_resposta_do_bot(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'oi', 'confianca' => 0.99, 'precisa_humano' => false]);

        Conversation::factory()->comHumano()->create([
            'tenant_id' => $this->tenant->id,
            'chat_id' => self::CHAT,
        ]);

        $this->postarFragmento('e aí?');
        $this->rodarFila();

        $this->whatsapp->assertNothingSent();
        $this->assertSame(1, Message::where('direcao', Message::ENTRADA)->count());
    }

    public function test_assinatura_vencida_para_o_bot(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'oi', 'confianca' => 0.99, 'precisa_humano' => false]);

        $this->tenant->forceFill(['ativo_ate' => today()->subDay()])->save();

        $this->postarFragmento('oi');
        $this->rodarFila();

        $this->whatsapp->assertNothingSent();
        $this->assertSame(0, Message::count());
    }

    public function test_teto_de_mensagens_para_o_bot(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'oi', 'confianca' => 0.99, 'precisa_humano' => false]);

        $this->tenant->forceFill(['mensagens_mes' => 3000, 'teto_mensagens_mes' => 3000])->save();

        $this->postarFragmento('oi');
        $this->rodarFila();

        $this->whatsapp->assertNothingSent();
    }

    public function test_custo_e_tokens_ficam_registrados(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'Pode vir sem agendar!', 'confianca' => 0.95, 'precisa_humano' => false]);

        $this->postarFragmento('precisa agendar?');
        $this->rodarFila();

        $saida = Message::where('direcao', Message::SAIDA)->first();

        $this->assertSame(820, $saida->tokens_entrada);
        $this->assertSame(44, $saida->tokens_saida);
        $this->assertSame(0.00012, $saida->custo_usd);
        $this->assertSame('google/gemini-2.5-flash-lite', $saida->modelo);

        $metrica = MetricDaily::first();
        $this->assertSame(1, $metrica->conversas);
        $this->assertSame(1, $metrica->resolvidas);
        $this->assertSame(820, $metrica->tokens_entrada);
        $this->assertSame(0.00012, $metrica->custo_usd);

        $this->assertSame(1, $this->tenant->fresh()->mensagens_mes);
    }

    public function test_a_chamada_ao_llm_pede_retencao_zero_e_modelo_fixado(): void
    {
        Queue::fake();
        $this->fingirLlm(['resposta' => 'Pode vir!', 'confianca' => 0.95, 'precisa_humano' => false]);

        $this->postarFragmento('tá aberto?');
        $this->rodarFila();

        Http::assertSent(function (\Illuminate\Http\Client\Request $r) {
            return $r['provider']['zdr'] === true
                && $r['model'] === 'google/gemini-2.5-flash-lite'
                && ! str_contains($r['model'], 'latest')
                && $r['models'][1] === 'openai/gpt-5-nano'
                && $r['response_format']['type'] === 'json_object';
        });
    }

    public function test_imagem_do_cliente_vira_registro_e_escala_para_humano(): void
    {
        Queue::fake();
        Notification::fake();

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/waha/mensagem-audio.json')), true);
        $payload['payload']['media']['mimetype'] = 'image/jpeg';
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->call('POST', '/api/webhooks/waha', [], [], [], [
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $corpo, self::CHAVE),
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (now()->getTimestamp() * 1000),
            'CONTENT_TYPE' => 'application/json',
        ], $corpo)->assertOk();

        $this->rodarFila();

        $this->assertSame('imagem', Message::first()->tipo);
        $this->assertSame(Conversation::ESTADO_HUMANO, Conversation::first()->estado);
        $this->whatsapp->assertSentText(self::CHAT, config('whatsapp.mensagem_escape'));
    }

    public function test_audio_e_baixado_na_hora_e_transcrito(): void
    {
        Queue::fake();

        Http::fake([
            'openrouter.ai/api/v1/audio/transcriptions' => Http::response(['text' => 'vocês atendem sábado?']),
            'openrouter.ai/*' => Http::response([
                'model' => 'google/gemini-2.5-flash-lite',
                'choices' => [['message' => ['content' => json_encode([
                    'resposta' => 'Sim, sábado das 9h às 13h!',
                    'confianca' => 0.94,
                    'precisa_humano' => false,
                ], JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 30, 'cost' => 0.0001],
            ]),
        ]);

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/waha/mensagem-audio.json')), true);
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->call('POST', '/api/webhooks/waha', [], [], [], [
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $corpo, self::CHAVE),
            'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (now()->getTimestamp() * 1000),
            'CONTENT_TYPE' => 'application/json',
        ], $corpo)->assertOk();

        $this->rodarFila();

        $entrada = Message::where('direcao', Message::ENTRADA)->first();

        $this->assertSame('audio', $entrada->tipo);
        $this->assertSame('vocês atendem sábado?', $entrada->transcricao);
        $this->assertNotEmpty($this->whatsapp->downloadedMedia());
        $this->whatsapp->assertSentText(self::CHAT, 'sábado das 9h às 13h');
    }
}

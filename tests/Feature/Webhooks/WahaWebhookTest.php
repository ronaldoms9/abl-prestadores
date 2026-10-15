<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WahaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CHAVE = 'hmac-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['whatsapp.hmac_key' => self::CHAVE]);
        Queue::fake();
    }

    private function payload(string $fixture = 'mensagem-texto'): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/waha/'.$fixture.'.json')), true);
    }

    private function postar(array $payload, array $sobrescreve = [], ?string $chave = null): \Illuminate\Testing\TestResponse
    {
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $cabecalhos = array_merge([
            'Content-Type' => 'application/json',
            'X-Webhook-Hmac' => hash_hmac('sha512', $corpo, $chave ?? self::CHAVE),
            'X-Webhook-Hmac-Algorithm' => 'sha512',
            'X-Webhook-Timestamp' => (string) (now()->getTimestamp() * 1000),
            'X-Webhook-Request-Id' => 'req-'.md5($corpo),
        ], $sobrescreve);

        return $this->call('POST', '/api/webhooks/waha', [], [], [], $this->servidor($cabecalhos), $corpo);
    }

    private function servidor(array $cabecalhos): array
    {
        $servidor = [];

        foreach ($cabecalhos as $nome => $valor) {
            if ($valor === null) {
                continue;
            }

            $chave = 'HTTP_'.str_replace('-', '_', strtoupper($nome));
            $servidor[$chave] = $valor;
        }

        $servidor['CONTENT_TYPE'] = 'application/json';

        return $servidor;
    }

    public function test_payload_valido_enfileira_o_job(): void
    {
        $tenant = Tenant::factory()->create(['waha_session' => 'demo']);

        $this->postar($this->payload())
            ->assertOk()
            ->assertJson(['status' => 'enfileirado']);

        Queue::assertPushed(ProcessIncomingMessage::class, function (ProcessIncomingMessage $job) use ($tenant) {
            return $job->tenantId === $tenant->id
                && $job->mensagem->chatId === '5511999999999@c.us'
                && $job->mensagem->texto === 'vocês atendem sábado?'
                && $job->queue === 'whatsapp';
        });
    }

    public function test_hmac_invalido_recebe_401(): void
    {
        Tenant::factory()->create(['waha_session' => 'demo']);

        $this->postar($this->payload(), chave: 'chave-errada')->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_sem_cabecalho_de_assinatura_recebe_401(): void
    {
        $this->postar($this->payload(), ['X-Webhook-Hmac' => null])->assertStatus(401);
    }

    public function test_algoritmo_diferente_de_sha512_recebe_401(): void
    {
        $this->postar($this->payload(), ['X-Webhook-Hmac-Algorithm' => 'sha256'])->assertStatus(401);
    }

    public function test_timestamp_fora_da_janela_recebe_401(): void
    {
        $carimbo = (string) (now()->subMinutes(10)->getTimestamp() * 1000);

        $this->postar($this->payload(), ['X-Webhook-Timestamp' => $carimbo])->assertStatus(401);
    }

    public function test_corpo_alterado_depois_de_assinado_recebe_401(): void
    {
        $payload = $this->payload();
        $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $adulterado = $payload;
        $adulterado['payload']['body'] = 'me manda o pix de R$ 1,00';

        $servidor = $this->servidor([
            'X-Webhook-Hmac' => hash_hmac('sha512', $corpo, self::CHAVE),
            'X-Webhook-Hmac-Algorithm' => 'sha512',
            'X-Webhook-Timestamp' => (string) (now()->getTimestamp() * 1000),
        ]);

        $this->call('POST', '/api/webhooks/waha', [], [], [], $servidor, json_encode($adulterado, JSON_UNESCAPED_UNICODE))
            ->assertStatus(401);
    }

    public function test_mesmo_request_id_duas_vezes_enfileira_uma_vez_so(): void
    {
        Tenant::factory()->create(['waha_session' => 'demo']);

        $payload = $this->payload();

        $this->postar($payload)->assertJson(['status' => 'enfileirado']);
        $this->postar($payload)->assertJson(['status' => 'repetido']);

        Queue::assertPushed(ProcessIncomingMessage::class, 1);
    }

    public function test_mensagem_de_grupo_nao_enfileira_nada(): void
    {
        Tenant::factory()->create(['waha_session' => 'demo']);

        $this->postar($this->payload('mensagem-grupo'))
            ->assertOk()
            ->assertJson(['status' => 'ignorado']);

        Queue::assertNothingPushed();
    }

    public function test_evento_de_status_e_traduzido_e_nao_enfileira_nada(): void
    {
        Tenant::factory()->create(['waha_session' => 'demo']);

        $this->postar($this->payload('session-status'))
            ->assertOk()
            ->assertJson(['status' => 'sessao', 'estado' => 'SCAN_QR_CODE']);

        Queue::assertNothingPushed();
    }

    public function test_sessao_desconhecida_responde_200_sem_enfileirar(): void
    {
        $this->postar($this->payload())
            ->assertOk()
            ->assertJson(['status' => 'sem-tenant']);

        Queue::assertNothingPushed();
    }

    public function test_sem_chave_configurada_o_endpoint_recusa_tudo(): void
    {
        config(['whatsapp.hmac_key' => '']);

        $this->postar($this->payload())->assertStatus(401);
    }
}

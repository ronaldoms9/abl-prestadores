<?php

namespace Tests\Feature\WhatsApp;

use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Enums\SessionState;
use App\WhatsApp\Fake\FakeWhatsAppProvider;
use App\WhatsApp\Waha\WahaException;
use App\WhatsApp\Waha\WahaMessageCappingException;
use App\WhatsApp\Waha\WahaProvider;
use App\WhatsApp\Waha\WahaTimelockException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahaProviderTest extends TestCase
{
    private function provider(): WahaProvider
    {
        return new WahaProvider(
            baseUrl: 'http://waha:3000',
            apiKey: 'chave-secreta',
            timeout: 5,
            webhookUrl: 'http://app/api/webhooks/waha',
            hmacKey: 'hmac-secreto',
        );
    }

    public function test_toda_chamada_leva_a_api_key(): void
    {
        Http::fake(['*' => Http::response(['result' => true])]);

        $this->provider()->sendText('demo', '5511999999999@c.us', 'oi');

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Api-Key', 'chave-secreta')
            && $r->url() === 'http://waha:3000/api/sendText'
            && $r['text'] === 'oi'
            && $r['chatId'] === '5511999999999@c.us'
            && $r['session'] === 'demo');
    }

    public function test_criar_sessao_configura_o_webhook_com_hmac(): void
    {
        Http::fake(['*' => Http::response(['name' => 'demo', 'status' => 'STARTING'], 201)]);

        $this->provider()->startSession('demo');

        Http::assertSent(function (Request $r) {
            $webhook = $r['config']['webhooks'][0];

            return $r->url() === 'http://waha:3000/api/sessions'
                && $r['name'] === 'demo'
                && $r['start'] === true
                && $webhook['url'] === 'http://app/api/webhooks/waha'
                && $webhook['hmac']['key'] === 'hmac-secreto'
                && in_array('message', $webhook['events'], true);
        });
    }

    public function test_sessao_ja_existente_apenas_inicia(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'Session already exists'], 422)
            ->push(['name' => 'demo', 'status' => 'STARTING'], 201);

        $this->provider()->startSession('demo');

        Http::assertSent(fn (Request $r) => $r->url() === 'http://waha:3000/api/sessions/demo/start');
    }

    public function test_status_vira_enum(): void
    {
        Http::fake(['*' => Http::response([
            'name' => 'demo',
            'status' => 'WORKING',
            'engine' => ['engine' => 'NOWEB'],
            'me' => ['id' => '5511999999999@c.us'],
        ])]);

        $snapshot = $this->provider()->getStatus('demo');

        $this->assertSame(SessionState::Working, $snapshot->state);
        $this->assertTrue($snapshot->state->pareada());
        $this->assertSame('NOWEB', $snapshot->engine);
    }

    public function test_qr_volta_como_png_em_base64(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

        $qr = $this->provider()->getQr('demo');

        $this->assertSame($png, base64_decode($qr, true));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'format=image'));
    }

    public function test_qr_em_json_com_data_tambem_e_aceito(): void
    {
        Http::fake(['*' => Http::response(['mimetype' => 'image/png', 'data' => 'QkFTRTY0'], 200, ['Content-Type' => 'application/json'])]);

        $this->assertSame('QkFTRTY0', $this->provider()->getQr('demo'));
    }

    public function test_qr_que_volta_como_string_de_pareamento_vira_erro(): void
    {
        // format=raw devolveria isto — e não é PNG.
        Http::fake(['*' => Http::response(['value' => 'https://wa.me/settings/linked_devices#2@abc'], 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(WahaException::class);

        $this->provider()->getQr('demo');
    }

    public function test_erro_463_vira_excecao_de_timelock(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Reachout timelock'], 463)]);

        $this->expectException(WahaTimelockException::class);

        $this->provider()->sendText('demo', '5511999999999@c.us', 'oi');
    }

    public function test_erro_475_carrega_o_aviso_previo_de_banimento(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Message capping', 'state' => 'SECOND_WARNING'], 475)]);

        try {
            $this->provider()->sendText('demo', '5511999999999@c.us', 'oi');
            $this->fail('deveria ter lançado WahaMessageCappingException');
        } catch (WahaMessageCappingException $e) {
            $this->assertSame('SECOND_WARNING', $e->aviso());
            $this->assertTrue($e->ehAvisoDeBanimento());
        }
    }

    public function test_erro_comum_vira_waha_exception_generica(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        try {
            $this->provider()->sendText('demo', '5511999999999@c.us', 'oi');
            $this->fail('deveria ter lançado WahaException');
        } catch (WahaException $e) {
            $this->assertNotInstanceOf(WahaTimelockException::class, $e);
            $this->assertSame(401, $e->status);
        }
    }

    public function test_binding_resolve_para_o_fake_em_teste(): void
    {
        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppProvider::class));
    }

    public function test_binding_resolve_para_o_waha_quando_o_driver_e_waha(): void
    {
        config(['whatsapp.driver' => 'waha']);
        app()->forgetInstance(WhatsAppProvider::class);

        $this->assertInstanceOf(WahaProvider::class, app(WhatsAppProvider::class));
    }
}

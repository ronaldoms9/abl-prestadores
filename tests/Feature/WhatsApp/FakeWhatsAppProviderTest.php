<?php

namespace Tests\Feature\WhatsApp;

use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Enums\SessionState;
use App\WhatsApp\Fake\FakeWhatsAppProvider;
use Tests\TestCase;

class FakeWhatsAppProviderTest extends TestCase
{
    public function test_guarda_textos_e_estados_de_digitando(): void
    {
        /** @var FakeWhatsAppProvider $fake */
        $fake = app(WhatsAppProvider::class);

        $fake->sendSeen('demo', '551199@c.us');
        $fake->startTyping('demo', '551199@c.us');
        $fake->stopTyping('demo', '551199@c.us');
        $fake->sendText('demo', '551199@c.us', 'atendemos sábado das 9h às 13h');

        $fake->assertSeen('551199@c.us');
        $fake->assertTyped('551199@c.us');
        $fake->assertSentText('551199@c.us', 'sábado');
        $fake->assertSentCount(1);

        $this->assertSame(['seen', 'typing.start', 'typing.stop', 'text'], $fake->sequencia('551199@c.us'));
    }

    public function test_sessoes_mudam_de_estado(): void
    {
        /** @var FakeWhatsAppProvider $fake */
        $fake = app(WhatsAppProvider::class);

        $this->assertSame(SessionState::Stopped, $fake->getStatus('demo')->state);

        $fake->startSession('demo');
        $this->assertSame(SessionState::ScanQrCode, $fake->getStatus('demo')->state);

        $fake->definirEstado('demo', SessionState::Working);
        $this->assertTrue($fake->getStatus('demo')->state->pareada());

        $fake->stopSession('demo');
        $this->assertSame(SessionState::Stopped, $fake->getStatus('demo')->state);
    }

    public function test_assert_nothing_sent(): void
    {
        /** @var FakeWhatsAppProvider $fake */
        $fake = app(WhatsAppProvider::class);

        $fake->assertNothingSent();
    }
}

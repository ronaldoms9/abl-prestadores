<?php

namespace Tests\Unit\WhatsApp;

use App\WhatsApp\Enums\MessageKind;
use App\WhatsApp\Enums\SessionState;
use App\WhatsApp\Waha\WahaWebhookTranslator;
use PHPUnit\Framework\TestCase;

class WahaWebhookTranslatorTest extends TestCase
{
    private WahaWebhookTranslator $tradutor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tradutor = new WahaWebhookTranslator();
    }

    private function fixture(string $nome): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../Fixtures/waha/'.$nome.'.json'), true);
    }

    public function test_traduz_mensagem_de_texto(): void
    {
        $mensagem = $this->tradutor->translate($this->fixture('mensagem-texto'));

        $this->assertNotNull($mensagem);
        $this->assertSame('demo', $mensagem->session);
        $this->assertSame('5511999999999@c.us', $mensagem->chatId);
        $this->assertSame('5511999999999', $mensagem->telefone());
        $this->assertSame(MessageKind::Texto, $mensagem->kind);
        $this->assertSame('vocês atendem sábado?', $mensagem->texto);
        $this->assertNull($mensagem->media);
        $this->assertFalse($mensagem->fromMe);
    }

    public function test_mensagem_de_grupo_vira_null(): void
    {
        $this->assertNull($this->tradutor->translate($this->fixture('mensagem-grupo')));
    }

    public function test_traduz_audio_com_a_referencia_de_midia(): void
    {
        $mensagem = $this->tradutor->translate($this->fixture('mensagem-audio'));

        $this->assertNotNull($mensagem);
        $this->assertSame(MessageKind::Audio, $mensagem->kind);
        $this->assertSame('audio/ogg', $mensagem->media->mimeBase());
        $this->assertSame('oga', $mensagem->media->extensao());
        $this->assertNull($mensagem->texto);
    }

    public function test_mensagem_do_proprio_dono_vira_null(): void
    {
        $payload = $this->fixture('mensagem-texto');
        $payload['payload']['fromMe'] = true;

        $this->assertNull($this->tradutor->translate($payload));
    }

    public function test_status_e_transmissao_viram_null(): void
    {
        foreach (['status@broadcast', '5511999999999@broadcast', '123@newsletter'] as $origem) {
            $payload = $this->fixture('mensagem-texto');
            $payload['payload']['from'] = $origem;

            $this->assertNull($this->tradutor->translate($payload), "deveria descartar {$origem}");
        }
    }

    public function test_figurinha_e_reacao_sao_descartadas_em_silencio(): void
    {
        $figurinha = $this->fixture('mensagem-audio');
        $figurinha['payload']['media']['mimetype'] = 'image/webp';

        $reacao = $this->fixture('mensagem-texto');
        $reacao['payload']['reaction'] = ['text' => '👍', 'messageId' => 'abc'];

        $this->assertNull($this->tradutor->translate($figurinha));
        $this->assertNull($this->tradutor->translate($reacao));
    }

    public function test_imagem_documento_localizacao_e_contato_nao_sao_descartados(): void
    {
        $imagem = $this->fixture('mensagem-audio');
        $imagem['payload']['media']['mimetype'] = 'image/jpeg';

        $documento = $this->fixture('mensagem-audio');
        $documento['payload']['media']['mimetype'] = 'application/pdf';

        $localizacao = $this->fixture('mensagem-texto');
        $localizacao['payload']['location'] = ['latitude' => -23.5, 'longitude' => -46.6];

        $contato = $this->fixture('mensagem-texto');
        $contato['payload']['vCards'] = ['BEGIN:VCARD...'];

        $esperado = [
            [$imagem, MessageKind::Imagem],
            [$documento, MessageKind::Documento],
            [$localizacao, MessageKind::Localizacao],
            [$contato, MessageKind::Contato],
        ];

        foreach ($esperado as [$payload, $kind]) {
            $mensagem = $this->tradutor->translate($payload);

            $this->assertNotNull($mensagem);
            $this->assertSame($kind, $mensagem->kind);
            $this->assertTrue($mensagem->kind->exigeHumano());
        }
    }

    public function test_evento_que_nao_e_mensagem_vira_null(): void
    {
        $this->assertNull($this->tradutor->translate($this->fixture('session-status')));
    }

    public function test_traduz_evento_de_status_de_sessao(): void
    {
        $snapshot = $this->tradutor->translateStatus($this->fixture('session-status'));

        $this->assertNotNull($snapshot);
        $this->assertSame('demo', $snapshot->session);
        $this->assertSame(SessionState::ScanQrCode, $snapshot->state);
        $this->assertTrue($snapshot->state->precisaDeQr());
    }

    public function test_timestamp_em_milissegundos_e_normalizado(): void
    {
        $payload = $this->fixture('mensagem-texto');
        $payload['payload']['timestamp'] = 1789000000000;

        $mensagem = $this->tradutor->translate($payload);

        $this->assertSame(1789000000, $mensagem->timestamp->getTimestamp());
    }
}

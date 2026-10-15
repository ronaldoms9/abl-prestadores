<?php

namespace App\WhatsApp\Fake;

use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Data\SessionSnapshot;
use App\WhatsApp\Enums\SessionState;
use PHPUnit\Framework\Assert;

/**
 * Provider em memória.
 *
 * É esta classe que economiza semanas: sem ela, todo teste do motor de conversa
 * exigiria um WhatsApp real pareado.
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    /** @var array<int, array{session: string, chatId: string, text: string}> */
    private array $enviadas = [];

    /** @var array<int, array{session: string, chatId: string, acao: string}> */
    private array $acoes = [];

    /** @var array<string, SessionState> */
    private array $sessoes = [];

    /** @var array<int, string> */
    private array $midiasBaixadas = [];

    public string $qrFalso = 'aVZCT1J3MEtHZ29BQUFBTlNVaEVVZ0FBAA==';

    public function startSession(string $session): void
    {
        $this->sessoes[$session] = SessionState::ScanQrCode;
    }

    public function stopSession(string $session): void
    {
        $this->sessoes[$session] = SessionState::Stopped;
    }

    public function getQr(string $session): string
    {
        return $this->qrFalso;
    }

    public function getStatus(string $session): SessionSnapshot
    {
        return new SessionSnapshot(
            session: $session,
            state: $this->sessoes[$session] ?? SessionState::Stopped,
            engine: 'NOWEB',
        );
    }

    public function sendSeen(string $session, string $chatId): void
    {
        $this->acoes[] = ['session' => $session, 'chatId' => $chatId, 'acao' => 'seen'];
    }

    public function startTyping(string $session, string $chatId): void
    {
        $this->acoes[] = ['session' => $session, 'chatId' => $chatId, 'acao' => 'typing.start'];
    }

    public function stopTyping(string $session, string $chatId): void
    {
        $this->acoes[] = ['session' => $session, 'chatId' => $chatId, 'acao' => 'typing.stop'];
    }

    public function sendText(string $session, string $chatId, string $text): void
    {
        $this->enviadas[] = ['session' => $session, 'chatId' => $chatId, 'text' => $text];
        $this->acoes[] = ['session' => $session, 'chatId' => $chatId, 'acao' => 'text'];
    }

    public function downloadMedia(string $url): string
    {
        $this->midiasBaixadas[] = $url;

        $caminho = tempnam(sys_get_temp_dir(), 'fake-media-');
        file_put_contents($caminho, 'conteudo-falso');

        return $caminho;
    }

    /** Define o estado que getStatus() vai devolver — para simular pareamento. */
    public function definirEstado(string $session, SessionState $estado): void
    {
        $this->sessoes[$session] = $estado;
    }

    public function limpar(): void
    {
        $this->enviadas = [];
        $this->acoes = [];
        $this->sessoes = [];
        $this->midiasBaixadas = [];
    }

    /** @return array<int, array{session: string, chatId: string, text: string}> */
    public function sentMessages(): array
    {
        return $this->enviadas;
    }

    /** @return array<int, string> */
    public function sentTexts(): array
    {
        return array_column($this->enviadas, 'text');
    }

    /** @return array<int, string> */
    public function downloadedMedia(): array
    {
        return $this->midiasBaixadas;
    }

    public function assertSentText(string $chatId, ?string $trecho = null): void
    {
        $doChat = array_values(array_filter($this->enviadas, fn ($m) => $m['chatId'] === $chatId));

        Assert::assertNotEmpty($doChat, "Nenhum texto enviado para {$chatId}.");

        if ($trecho !== null) {
            $textos = array_column($doChat, 'text');

            Assert::assertTrue(
                (bool) array_filter($textos, fn ($t) => str_contains($t, $trecho)),
                "Nenhum texto enviado para {$chatId} contém \"{$trecho}\". Enviados: ".json_encode($textos, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    public function assertNothingSent(?string $chatId = null): void
    {
        $enviadas = $chatId === null
            ? $this->enviadas
            : array_filter($this->enviadas, fn ($m) => $m['chatId'] === $chatId);

        Assert::assertEmpty($enviadas, 'Esperava nenhum envio, mas houve: '.json_encode($enviadas, JSON_UNESCAPED_UNICODE));
    }

    public function assertSentCount(int $esperado, ?string $chatId = null): void
    {
        $enviadas = $chatId === null
            ? $this->enviadas
            : array_filter($this->enviadas, fn ($m) => $m['chatId'] === $chatId);

        Assert::assertCount($esperado, $enviadas, 'Envios: '.json_encode(array_values($enviadas), JSON_UNESCAPED_UNICODE));
    }

    public function assertTyped(string $chatId): void
    {
        $digitou = array_filter(
            $this->acoes,
            fn ($a) => $a['chatId'] === $chatId && $a['acao'] === 'typing.start'
        );

        Assert::assertNotEmpty($digitou, "Não houve 'digitando...' para {$chatId}.");
    }

    public function assertSeen(string $chatId): void
    {
        $visto = array_filter($this->acoes, fn ($a) => $a['chatId'] === $chatId && $a['acao'] === 'seen');

        Assert::assertNotEmpty($visto, "A mensagem de {$chatId} não foi marcada como lida.");
    }

    /** @return array<int, string> Ações e envios em ordem cronológica, para checar a sequência. */
    public function sequencia(string $chatId): array
    {
        $doChat = array_filter($this->acoes, fn ($a) => $a['chatId'] === $chatId);

        return array_values(array_column($doChat, 'acao'));
    }
}

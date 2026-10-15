<?php

namespace App\WhatsApp\Waha;

use App\WhatsApp\Data\IncomingMessage;
use App\WhatsApp\Data\MediaRef;
use App\WhatsApp\Data\SessionSnapshot;
use App\WhatsApp\Enums\MessageKind;
use Carbon\CarbonImmutable;

/**
 * Único lugar do sistema que entende o formato do WAHA.
 *
 * Devolve null para tudo que não vira conversa — é aqui que moram os filtros de
 * grupo, status, mensagem do próprio dono, figurinha e reação.
 */
class WahaWebhookTranslator
{
    private const EVENTOS_DE_MENSAGEM = ['message', 'message.any'];

    public function translate(array $payload): ?IncomingMessage
    {
        $evento = $payload['event'] ?? null;

        if (! in_array($evento, self::EVENTOS_DE_MENSAGEM, true)) {
            return null;
        }

        $dados = $payload['payload'] ?? [];
        $de = (string) ($dados['from'] ?? '');

        if ($de === '') {
            return null;
        }

        // Grupo, lista de transmissão e status não viram conversa.
        if (str_ends_with($de, '@g.us') || str_contains($de, '@broadcast') || str_contains($de, '@newsletter')) {
            return null;
        }

        // Mensagem do próprio dono no WhatsApp dele.
        if (($dados['fromMe'] ?? false) === true) {
            return null;
        }

        $media = is_array($dados['media'] ?? null) ? MediaRef::doPayload($dados['media']) : null;
        $kind = $this->kind($dados, $media);

        // Figurinha e reação: descartadas em silêncio.
        if ($kind->descartavel()) {
            return null;
        }

        $texto = $dados['body'] ?? null;

        return new IncomingMessage(
            session: (string) ($payload['session'] ?? ''),
            chatId: $de,
            messageId: (string) ($dados['id'] ?? $payload['id'] ?? ''),
            kind: $kind,
            texto: is_string($texto) && trim($texto) !== '' ? $texto : null,
            media: $media,
            fromMe: false,
            timestamp: $this->timestamp($dados['timestamp'] ?? null),
        );
    }

    /** Eventos de ciclo de vida da sessão — não viram conversa, mas alimentam o painel. */
    public function translateStatus(array $payload): ?SessionSnapshot
    {
        if (($payload['event'] ?? null) !== 'session.status') {
            return null;
        }

        $dados = $payload['payload'] ?? [];

        return new SessionSnapshot(
            session: (string) ($dados['name'] ?? $payload['session'] ?? ''),
            state: \App\WhatsApp\Enums\SessionState::deWaha($dados['status'] ?? null),
            engine: $payload['engine'] ?? null,
        );
    }

    private function kind(array $dados, ?MediaRef $media): MessageKind
    {
        if (isset($dados['reaction'])) {
            return MessageKind::Reacao;
        }

        if (isset($dados['location']) || $this->temNoData($dados, 'locationMessage')) {
            return MessageKind::Localizacao;
        }

        if (! empty($dados['vCards']) || $this->temNoData($dados, 'contactMessage')) {
            return MessageKind::Contato;
        }

        if ($media === null) {
            return ($dados['hasMedia'] ?? false) ? MessageKind::Outro : MessageKind::Texto;
        }

        if ($this->temNoData($dados, 'stickerMessage')) {
            return MessageKind::Figurinha;
        }

        return match (true) {
            str_starts_with((string) $media->mimeBase(), 'audio/') => MessageKind::Audio,
            // Figurinha do WhatsApp é webp; foto de cliente é jpeg/png.
            $media->mimeBase() === 'image/webp' => MessageKind::Figurinha,
            str_starts_with((string) $media->mimeBase(), 'image/') => MessageKind::Imagem,
            str_starts_with((string) $media->mimeBase(), 'video/') => MessageKind::Documento,
            $media->mimeBase() !== null => MessageKind::Documento,
            default => MessageKind::Outro,
        };
    }

    private function temNoData(array $dados, string $chave): bool
    {
        $cru = $dados['_data'] ?? null;

        if (! is_array($cru)) {
            return false;
        }

        return isset($cru['Message'][$chave]) || isset($cru['message'][$chave]) || isset($cru[$chave]);
    }

    private function timestamp(mixed $valor): CarbonImmutable
    {
        if (! is_numeric($valor)) {
            return CarbonImmutable::now();
        }

        $valor = (int) $valor;

        // O WAHA manda segundos; alguns eventos vêm em milissegundos.
        return CarbonImmutable::createFromTimestamp($valor > 99999999999 ? intdiv($valor, 1000) : $valor);
    }
}

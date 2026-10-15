<?php

namespace App\WhatsApp\Enums;

enum MessageKind: string
{
    case Texto = 'texto';
    case Audio = 'audio';
    case Imagem = 'imagem';
    case Documento = 'documento';
    case Localizacao = 'localizacao';
    case Contato = 'contato';
    case Figurinha = 'figurinha';
    case Reacao = 'reacao';
    case Outro = 'outro';

    /** Não vira conversa e não merece resposta: descartar em silêncio. */
    public function descartavel(): bool
    {
        return in_array($this, [self::Figurinha, self::Reacao], true);
    }

    /** O bot não lida com isso: registra e escala para humano. */
    public function exigeHumano(): bool
    {
        return in_array($this, [
            self::Imagem,
            self::Documento,
            self::Localizacao,
            self::Contato,
            self::Outro,
        ], true);
    }

    /** Tem conteúdo textual utilizável direto (áudio só depois de transcrito). */
    public function ehConversavel(): bool
    {
        return in_array($this, [self::Texto, self::Audio], true);
    }
}

<?php

namespace App\WhatsApp\Data;

final readonly class MediaRef
{
    public function __construct(
        public string $url,
        public ?string $mimetype = null,
        public ?string $filename = null,
    ) {
    }

    public static function doPayload(array $media): ?self
    {
        $url = $media['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        return new self(
            url: $url,
            mimetype: $media['mimetype'] ?? null,
            filename: $media['filename'] ?? null,
        );
    }

    /** 'audio/ogg; codecs=opus' -> 'audio/ogg' */
    public function mimeBase(): ?string
    {
        if ($this->mimetype === null) {
            return null;
        }

        return trim(explode(';', $this->mimetype)[0]);
    }

    public function extensao(): string
    {
        return match ($this->mimeBase()) {
            'audio/ogg' => 'oga',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => pathinfo((string) $this->filename, PATHINFO_EXTENSION) ?: 'bin',
        };
    }
}

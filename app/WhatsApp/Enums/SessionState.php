<?php

namespace App\WhatsApp\Enums;

enum SessionState: string
{
    case Starting = 'STARTING';
    case ScanQrCode = 'SCAN_QR_CODE';
    case Working = 'WORKING';
    case Failed = 'FAILED';
    case Stopped = 'STOPPED';

    /** Valores crus do WAHA só viram enum aqui dentro. */
    public static function deWaha(?string $valor): self
    {
        return self::tryFrom(strtoupper((string) $valor)) ?? self::Failed;
    }

    public function pareada(): bool
    {
        return $this === self::Working;
    }

    public function precisaDeQr(): bool
    {
        return $this === self::ScanQrCode;
    }
}

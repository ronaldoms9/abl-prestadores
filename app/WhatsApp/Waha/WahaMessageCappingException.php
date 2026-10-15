<?php

namespace App\WhatsApp\Waha;

/**
 * 475 — Message Capping.
 *
 * Cota de contatos novos por ciclo. Reiniciar a sessão não zera a cota. Os
 * estados FIRST_WARNING e SECOND_WARNING são o aviso prévio do banimento — o
 * único sinal que existe antes de perder o número, e por isso precisam chegar
 * a um humano.
 */
class WahaMessageCappingException extends WahaException
{
    public function aviso(): ?string
    {
        $aviso = $this->corpo['state'] ?? $this->corpo['status'] ?? null;

        return is_string($aviso) ? strtoupper($aviso) : null;
    }

    public function ehAvisoDeBanimento(): bool
    {
        return in_array($this->aviso(), ['FIRST_WARNING', 'SECOND_WARNING'], true);
    }
}

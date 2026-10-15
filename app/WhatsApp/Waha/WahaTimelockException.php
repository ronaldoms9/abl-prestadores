<?php

namespace App\WhatsApp\Waha;

/**
 * 463 — Reachout Timelock.
 *
 * Restrição silenciosa do WhatsApp: a sessão continua WORKING. Nunca reiniciar,
 * deslogar ou reparear por causa disso — passa sozinho. A única reação correta
 * é parar de mandar para aquele contato e tentar mais tarde.
 */
class WahaTimelockException extends WahaException
{
}

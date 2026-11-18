<?php

namespace App\Events;

use App\Models\NotaFiscal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A nota foi aprovada pelo financeiro. A fatia 6 da E5 (título no UAU) passa a ouvir este evento. */
class NotaAprovada
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly NotaFiscal $nota)
    {
    }
}

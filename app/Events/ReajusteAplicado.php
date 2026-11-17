<?php

namespace App\Events;

use App\Models\Reajuste;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Um reajuste foi gravado (individual ou em lote), com as novas linhas de
 * remuneração. A Entrega 3 ouve este evento para gerar o aditivo de contrato.
 * É disparado depois do commit da transação.
 */
class ReajusteAplicado
{
    use Dispatchable;

    /** @param Collection<int, \App\Models\Remuneracao> $remuneracoes */
    public function __construct(public readonly Reajuste $reajuste, public readonly Collection $remuneracoes)
    {
    }
}

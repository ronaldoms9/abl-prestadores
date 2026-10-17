<?php

namespace App\Integrations\Uau;

use App\Models\Sincronizacao;
use RuntimeException;

/** Pedido de sincronização com outra ainda em andamento (D8.2 da fatia 8). */
class SincronizacaoEmAndamento extends RuntimeException
{
    public function __construct(public readonly Sincronizacao $emAndamento)
    {
        parent::__construct(sprintf(
            'Já há uma sincronização em andamento, iniciada às %s. Aguarde a conclusão.',
            $emAndamento->iniciada_em->format('H:i'),
        ));
    }
}

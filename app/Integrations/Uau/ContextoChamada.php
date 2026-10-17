<?php

namespace App\Integrations\Uau;

use Illuminate\Database\Eloquent\Model;

/** A que se refere uma chamada ao UAU, para o registro em integracao_logs. */
final readonly class ContextoChamada
{
    public function __construct(
        public ?Model $entidade = null,
        public ?string $chaveIdempotencia = null,
        public int $tentativa = 1,
    ) {
    }
}

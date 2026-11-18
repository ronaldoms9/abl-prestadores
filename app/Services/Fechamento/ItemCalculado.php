<?php

namespace App\Services\Fechamento;

use App\Enums\TipoItemFechamento;
use Illuminate\Database\Eloquent\Model;

/** Uma linha da memória de cálculo do fechamento. Valores em centavos. */
final class ItemCalculado
{
    public function __construct(
        public readonly TipoItemFechamento $tipo,
        public readonly string $descricao,
        public readonly float $quantidade,
        public readonly int $valorUnitario,
        public readonly int $total,
        public readonly ?Model $origem = null,
    ) {
    }
}

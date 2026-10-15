<?php

namespace App\Bot;

final readonly class PriceVerdict
{
    /** @param  array<int, float>  $inventados */
    private function __construct(
        public bool $liberado,
        public array $inventados = [],
    ) {
    }

    public static function liberado(): self
    {
        return new self(true);
    }

    /** @param  array<int, float>  $inventados */
    public static function bloqueado(array $inventados): self
    {
        return new self(false, $inventados);
    }

    public function motivo(): string
    {
        return 'Preço fora da lista de produtos: '.implode(', ', array_map(
            fn (float $p) => 'R$ '.number_format($p, 2, ',', '.'),
            $this->inventados
        ));
    }
}

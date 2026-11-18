<?php

namespace App\Services\NotaFiscal;

use Carbon\CarbonInterface;

/** O que um leitor de XML extraiu da nota. Valores em centavos; CNPJs só com dígitos. */
final class NotaLida
{
    /** @param  array<string, int>  $retencoes  tributo (ISS, IRRF...) => centavos */
    public function __construct(
        public readonly string $layout,
        public readonly ?string $numero,
        public readonly ?string $serie,
        public readonly ?string $chaveAcesso,
        public readonly ?CarbonInterface $emissao,
        public readonly ?string $cnpjEmissor,
        public readonly ?string $cnpjTomador,
        public readonly ?int $valorBruto,
        public readonly ?int $valorLiquido,
        public readonly array $retencoes = [],
    ) {
    }

    public function totalRetencoes(): int
    {
        return array_sum($this->retencoes);
    }
}

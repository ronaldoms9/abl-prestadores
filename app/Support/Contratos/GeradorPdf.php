<?php

namespace App\Support\Contratos;

/** Converte o HTML do contrato em PDF. A implementação padrão usa o dompdf, quando instalado. */
interface GeradorPdf
{
    /** @return string Bytes do PDF. */
    public function gerar(string $html): string;
}

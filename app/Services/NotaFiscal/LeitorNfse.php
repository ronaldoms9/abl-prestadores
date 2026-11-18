<?php

namespace App\Services\NotaFiscal;

use DOMDocument;

/** Um layout de NFS-e: diz se reconhece o XML e extrai os dados. */
interface LeitorNfse
{
    public function suporta(DOMDocument $xml): bool;

    public function ler(DOMDocument $xml): NotaLida;
}

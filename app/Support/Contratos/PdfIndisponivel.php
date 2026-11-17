<?php

namespace App\Support\Contratos;

use RuntimeException;

/** Usado enquanto a biblioteca de PDF não está instalada: falha com uma mensagem clara em vez de gerar um arquivo vazio. */
class PdfIndisponivel implements GeradorPdf
{
    public function gerar(string $html): string
    {
        throw new RuntimeException('A biblioteca de PDF (dompdf) não está instalada. Rode "composer require dompdf/dompdf" no servidor.');
    }
}

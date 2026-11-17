<?php

namespace App\Support\Contratos;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Gera o PDF com o dompdf (A4, fonte serifada), sem acessar endereços externos. */
class DompdfGerador implements GeradorPdf
{
    public function gerar(string $html): string
    {
        $opcoes = new Options;
        $opcoes->set('isRemoteEnabled', false);
        $opcoes->set('isPhpEnabled', false);
        $opcoes->set('defaultFont', 'DejaVu Serif');

        $pdf = new Dompdf($opcoes);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}

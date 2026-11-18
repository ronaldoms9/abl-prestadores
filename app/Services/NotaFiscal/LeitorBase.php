<?php

namespace App\Services\NotaFiscal;

use App\Support\Dinheiro;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use Throwable;

/** Consultas por nome local, para não depender do namespace de cada município. */
abstract class LeitorBase implements LeitorNfse
{
    protected function existe(DOMDocument $xml, string $caminho): bool
    {
        return (new DOMXPath($xml))->query($this->xpath($caminho))->length > 0;
    }

    /** Primeiro valor não vazio entre os caminhos (ex.: "InfNfse/Numero"), ou null. */
    protected function texto(DOMDocument $xml, string ...$caminhos): ?string
    {
        $xpath = new DOMXPath($xml);

        foreach ($caminhos as $caminho) {
            $nos = $xpath->query($this->xpath($caminho));
            if ($nos !== false && $nos->length > 0 && trim($nos->item(0)->textContent) !== '') {
                return trim($nos->item(0)->textContent);
            }
        }

        return null;
    }

    protected function cnpj(DOMDocument $xml, string ...$caminhos): ?string
    {
        $valor = $this->texto($xml, ...$caminhos);
        $digitos = $valor === null ? '' : preg_replace('/\D+/', '', $valor);

        return $digitos === '' ? null : $digitos;
    }

    protected function centavos(DOMDocument $xml, string ...$caminhos): ?int
    {
        $valor = $this->texto($xml, ...$caminhos);

        if ($valor === null || ! is_numeric(str_replace(',', '.', $valor))) {
            return null;
        }

        return Dinheiro::centavos(str_replace(',', '.', $valor));
    }

    protected function data(DOMDocument $xml, string ...$caminhos): ?CarbonInterface
    {
        $valor = $this->texto($xml, ...$caminhos);

        try {
            return $valor === null ? null : Carbon::parse($valor)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** "A/B/C" vira //*[local-name()='A']/*[local-name()='B']/*[local-name()='C']. */
    private function xpath(string $caminho): string
    {
        $partes = array_map(fn (string $p) => "*[local-name()='{$p}']", explode('/', $caminho));

        return '//'.implode('/', $partes);
    }
}

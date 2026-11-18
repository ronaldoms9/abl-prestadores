<?php

namespace App\Services\NotaFiscal;

use DOMDocument;

/**
 * NFS-e Nacional (padrão ADN): NFSe/infNFSe com emit, valores e a DPS embutida.
 * A chave de acesso é o Id do infNFSe sem o prefixo "NFS".
 */
class LeitorNfseNacional extends LeitorBase
{
    public function suporta(DOMDocument $xml): bool
    {
        return $this->existe($xml, 'infNFSe/nNFSe') || $this->existe($xml, 'infNFSe/emit');
    }

    public function ler(DOMDocument $xml): NotaLida
    {
        $retencoes = array_filter([
            'ISS' => $this->centavos($xml, 'infNFSe/valores/vISSQN', 'infDPS/valores/trib/tribMun/vISSQN'),
            'IRRF' => $this->centavos($xml, 'infDPS/valores/trib/tribFed/vRetIRRF'),
            'INSS' => $this->centavos($xml, 'infDPS/valores/trib/tribFed/vRetCP'),
            'PIS' => $this->centavos($xml, 'infDPS/valores/trib/tribFed/piscofins/vPis'),
            'COFINS' => $this->centavos($xml, 'infDPS/valores/trib/tribFed/piscofins/vCofins'),
            'CSLL' => $this->centavos($xml, 'infDPS/valores/trib/tribFed/vRetCSLL'),
        ], fn ($v) => $v !== null && $v > 0);

        // Só conta como retenção o ISS que o tomador retém (tpRetISSQN = 2).
        if ($this->texto($xml, 'infDPS/valores/trib/tribMun/tpRetISSQN') !== '2') {
            unset($retencoes['ISS']);
        }

        $bruto = $this->centavos($xml, 'infDPS/valores/vServPrest/vServ');
        $chave = $this->chave($xml);

        return new NotaLida(
            'nacional',
            $this->texto($xml, 'infNFSe/nNFSe'),
            $this->texto($xml, 'infDPS/serie'),
            $chave,
            $this->data($xml, 'infDPS/dhEmi', 'infNFSe/dhProc'),
            $this->cnpj($xml, 'infNFSe/emit/CNPJ', 'infDPS/prest/CNPJ'),
            $this->cnpj($xml, 'infDPS/toma/CNPJ'),
            $bruto,
            $this->centavos($xml, 'infNFSe/valores/vLiq') ?? ($bruto === null ? null : $bruto - array_sum($retencoes)),
            $retencoes,
        );
    }

    private function chave(DOMDocument $xml): ?string
    {
        $id = $xml->getElementsByTagNameNS('*', 'infNFSe')->item(0)?->attributes?->getNamedItem('Id')?->nodeValue;
        $digitos = $id === null ? '' : preg_replace('/\D+/', '', $id);

        return $digitos === '' ? null : $digitos;
    }
}

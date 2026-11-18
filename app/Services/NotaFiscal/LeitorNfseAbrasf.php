<?php

namespace App\Services\NotaFiscal;

use DOMDocument;

/**
 * NFS-e no padrão ABRASF 2.x (InfNfse / Servico / Valores), base de grande
 * parte dos municípios. Só lê o que a conferência usa.
 */
class LeitorNfseAbrasf extends LeitorBase
{
    public function suporta(DOMDocument $xml): bool
    {
        return $this->existe($xml, 'InfNfse/Numero') || $this->existe($xml, 'InfNfse/Servico/Valores');
    }

    public function ler(DOMDocument $xml): NotaLida
    {
        $retencoes = array_filter([
            'ISS' => $this->issRetido($xml) ? $this->centavos($xml, 'InfNfse/Servico/Valores/ValorIss') : null,
            'IRRF' => $this->centavos($xml, 'InfNfse/Servico/Valores/ValorIr'),
            'INSS' => $this->centavos($xml, 'InfNfse/Servico/Valores/ValorInss'),
            'PIS' => $this->centavos($xml, 'InfNfse/Servico/Valores/ValorPis'),
            'COFINS' => $this->centavos($xml, 'InfNfse/Servico/Valores/ValorCofins'),
            'CSLL' => $this->centavos($xml, 'InfNfse/Servico/Valores/ValorCsll'),
        ], fn ($v) => $v !== null && $v > 0);

        $bruto = $this->centavos($xml, 'InfNfse/Servico/Valores/ValorServicos');
        $liquido = $this->centavos($xml, 'InfNfse/ValoresNfse/ValorLiquidoNfse', 'InfNfse/Servico/Valores/ValorLiquidoNfse')
            ?? ($bruto === null ? null : $bruto - array_sum($retencoes));

        return new NotaLida(
            'abrasf',
            $this->texto($xml, 'InfNfse/Numero'),
            $this->texto($xml, 'InfNfse/DeclaracaoPrestacaoServico/InfDeclaracaoPrestacaoServico/Rps/IdentificacaoRps/Serie', 'InfNfse/IdentificacaoRps/Serie'),
            $this->texto($xml, 'InfNfse/CodigoVerificacao'),
            $this->data($xml, 'InfNfse/DataEmissao'),
            $this->cnpj($xml, 'InfNfse/PrestadorServico/IdentificacaoPrestador/CpfCnpj/Cnpj', 'InfNfse/PrestadorServico/IdentificacaoPrestador/Cnpj'),
            $this->cnpj($xml, 'InfNfse/TomadorServico/IdentificacaoTomador/CpfCnpj/Cnpj', 'InfNfse/TomadorServico/IdentificacaoTomador/Cnpj'),
            $bruto,
            $liquido,
            $retencoes,
        );
    }

    /** IssRetido: 1 = sim, 2 = não. */
    private function issRetido(DOMDocument $xml): bool
    {
        return $this->texto($xml, 'InfNfse/Servico/Valores/IssRetido') === '1';
    }
}

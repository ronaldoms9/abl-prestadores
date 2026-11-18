<?php

namespace App\Services\NotaFiscal;

use DOMDocument;

/**
 * Porta de entrada da leitura: valida e protege o XML, escolhe o leitor do
 * layout e devolve a NotaLida. Layout novo = leitor novo na lista LEITORES.
 */
class LeitorXmlNfse
{
    public const TAMANHO_MAXIMO = 2 * 1024 * 1024;

    /** @var list<class-string<LeitorNfse>> */
    public const LEITORES = [LeitorNfseNacional::class, LeitorNfseAbrasf::class];

    public function ler(string $conteudo): NotaLida
    {
        $xml = $this->carregar($conteudo);

        foreach (self::LEITORES as $classe) {
            /** @var LeitorNfse $leitor */
            $leitor = app($classe);

            if ($leitor->suporta($xml)) {
                return $leitor->ler($xml);
            }
        }

        throw new XmlNaoLido('Não foi possível reconhecer o layout deste XML. Informe os dados da nota à mão.');
    }

    private function carregar(string $conteudo): DOMDocument
    {
        if (strlen($conteudo) > self::TAMANHO_MAXIMO) {
            throw new XmlNaoLido('O XML é grande demais para uma nota fiscal.');
        }

        // Sem DTD nem entidades: fecha a porta para XXE e para expansão de entidades.
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $conteudo)) {
            throw new XmlNaoLido('O XML contém declarações não permitidas (DOCTYPE ou ENTITY) e foi recusado.');
        }

        $usuario = libxml_use_internal_errors(true);
        $documento = new DOMDocument;
        $ok = $documento->loadXML($conteudo, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($usuario);

        if (! $ok) {
            throw new XmlNaoLido('O arquivo não é um XML válido.');
        }

        return $documento;
    }
}

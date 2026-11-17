<?php

namespace App\Support\Contratos;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Limpa o HTML do modelo antes de salvar: tira scripts, quadros, formulários
 * e atributos de evento, e endereços perigosos. O corpo é escrito pelo admin,
 * mas é mostrado na tela e vira PDF, então não pode executar nada. Formatação
 * (negrito, listas, tabelas, estilos) continua.
 */
class SanitizadorHtml
{
    private const ELEMENTOS_PROIBIDOS = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'audio', 'video', 'source', 'svg', 'math'];

    public static function limpar(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $documento = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        // O prefixo XML força UTF-8 sem converter as entidades do texto.
        $documento->loadHTML('<?xml encoding="UTF-8"><div id="__raiz">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $xpath = new DOMXPath($documento);

        foreach (self::ELEMENTOS_PROIBIDOS as $nome) {
            foreach (iterator_to_array($xpath->query('//'.$nome)) as $elemento) {
                $elemento->parentNode?->removeChild($elemento);
            }
        }

        /** @var DOMElement $elemento */
        foreach ($xpath->query('//*') as $elemento) {
            foreach (iterator_to_array($elemento->attributes) as $atributo) {
                $nome = strtolower($atributo->nodeName);
                $valor = strtolower(preg_replace('/[\x00-\x20]+/', '', $atributo->nodeValue));

                $perigoso = str_starts_with($nome, 'on')
                    || (in_array($nome, ['href', 'src', 'action', 'formaction', 'xlink:href', 'background'], true) && self::enderecoPerigoso($valor))
                    || ($nome === 'style' && (str_contains($valor, 'expression(') || str_contains($valor, 'javascript:') || str_contains($valor, 'url(')));

                if ($perigoso) {
                    $elemento->removeAttribute($atributo->nodeName);
                }
            }
        }

        $raiz = $documento->getElementById('__raiz') ?? $xpath->query('//div[@id="__raiz"]')->item(0);
        $saida = '';
        foreach ($raiz->childNodes as $filho) {
            $saida .= $documento->saveHTML($filho);
        }

        return trim($saida);
    }

    private static function enderecoPerigoso(string $valor): bool
    {
        if (str_starts_with($valor, 'javascript:') || str_starts_with($valor, 'vbscript:')) {
            return true;
        }

        // data: só para imagem.
        return str_starts_with($valor, 'data:') && ! str_starts_with($valor, 'data:image/');
    }
}

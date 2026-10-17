<?php

use App\Support\Documento;

if (!function_exists('validaCPF')) {
    /** Mantido para o código antigo; a regra está em App\Rules\Cpf. */
    function validaCPF($cpf)
    {
        if (filter_var($cpf, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return \App\Rules\Cpf::valido((string) $cpf);
    }
}


if (!function_exists('filtraNumeros')) {
    /** Só os dígitos, como texto ('' quando não há nenhum). Ver Documento::digitos(). */
    function filtraNumeros($input)
    {
        return Documento::digitos($input === null ? null : (string) $input) ?? '';
    }
}


if (!function_exists('hex2rgb')) {
    /**
     * Converte uma cor hexadecimal ("#0d6efd" ou "#06f") para "13, 110, 253",
     * o formato das variáveis --bs-*-rgb. Retorna null se a cor for inválida.
     */
    function hex2rgb(?string $hex): ?string
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        return implode(', ', array_map('hexdec', str_split($hex, 2)));
    }
}

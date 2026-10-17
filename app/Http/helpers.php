<?php

if (!function_exists('validaCPF')) {
    function validaCPF($cpf)
    {

        if (filter_var($cpf, FILTER_VALIDATE_EMAIL))
            return false;
        // Extrai somente os números
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        // Verifica se foi informado todos os digitos corretamente
        if (strlen($cpf) != 11)
            return false;

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        // Faz o calculo para validar o CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;

    }
}


if (!function_exists('filtraNumeros')) {
    function filtraNumeros($input)
    {
        return preg_replace('/[^0-9]/', '', $input);
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

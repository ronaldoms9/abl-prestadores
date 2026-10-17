<?php

namespace App\Support;

class Documento
{
    /**
     * Remove tudo que não é dígito. Vazio ou null vira null.
     */
    public static function digitos(?string $documento): ?string
    {
        if ($documento === null) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $documento);

        return $digitos === '' ? null : $digitos;
    }

    /**
     * Formata CPF (11 dígitos) ou CNPJ (14 dígitos). Outro tamanho volta como está.
     */
    public static function formatar(?string $documento): ?string
    {
        if ($documento === null || $documento === '') {
            return $documento;
        }

        $digitos = self::digitos($documento);

        if ($digitos === null) {
            return $documento;
        }

        if (strlen($digitos) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digitos);
        }

        if (strlen($digitos) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digitos);
        }

        return $documento;
    }
}

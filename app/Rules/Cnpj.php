<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ numérico com dígito verificador válido, com ou sem máscara.
 *
 * O CNPJ alfanumérico (IN RFB 2.229/2024) ainda não é aceito: o UAU e as
 * colunas atuais guardam só dígitos. Ver o risco no plano da fatia 5b.
 */
class Cnpj implements ValidationRule
{
    private const PESOS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function valido(?string $cnpj): bool
    {
        if ($cnpj === null || preg_match('/[A-Za-z]/', $cnpj)) {
            return false;
        }

        $cnpj = Documento::digitos($cnpj);

        if ($cnpj === null || strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $posicao) {
            $soma = 0;
            $pesos = array_slice(self::PESOS, 13 - $posicao);
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$i];
            }

            $resto = $soma % 11;
            if ((int) $cnpj[$posicao] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! self::valido((string) $value)) {
            $fail('O :attribute não é um CNPJ válido.');
        }
    }
}

<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com dígito verificador válido, com ou sem máscara.
 * valido() é a regra em si, usada também fora de formulários (carga da planilha).
 */
class Cpf implements ValidationRule
{
    public static function valido(?string $cpf): bool
    {
        $cpf = Documento::digitos($cpf);

        if ($cpf === null || strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }

            if ((int) $cpf[$posicao] !== ((10 * $soma) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! self::valido((string) $value)) {
            $fail('O :attribute não é um CPF válido.');
        }
    }
}

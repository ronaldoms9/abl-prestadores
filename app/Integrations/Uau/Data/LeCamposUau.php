<?php

namespace App\Integrations\Uau\Data;

use App\Integrations\Uau\RespostaUau;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Leitura dos campos do UAU: texto vazio vira null, a data "vazia" do .NET
 * (0001-01-01) vira null e o nome do campo não diferencia maiúsculas.
 */
trait LeCamposUau
{
    protected static function texto(array $linha, string $campo): ?string
    {
        $valor = RespostaUau::valor($linha, $campo);

        if (! is_string($valor) && ! is_int($valor) && ! is_float($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    protected static function inteiro(array $linha, string $campo): ?int
    {
        $valor = RespostaUau::valor($linha, $campo);

        return is_numeric($valor) ? (int) $valor : null;
    }

    protected static function data(array $linha, string $campo): ?CarbonImmutable
    {
        $texto = self::texto($linha, $campo);

        if ($texto === null || str_starts_with($texto, '0001-01-01')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($texto);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    protected static function lista(array $linha, string $campo): array
    {
        return RespostaUau::linhas(RespostaUau::valor($linha, $campo));
    }

    /** Campo obrigatório: sem ele a linha não identifica o registro no UAU. */
    protected static function obrigatorio(mixed $valor, string $campo): mixed
    {
        if ($valor === null) {
            throw new InvalidArgumentException("campo {$campo} ausente na resposta do UAU");
        }

        return $valor;
    }
}

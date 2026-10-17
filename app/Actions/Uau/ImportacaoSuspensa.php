<?php

namespace App\Actions\Uau;

use RuntimeException;

/**
 * A lista do UAU é suspeita e a importação parou sem gravar nada (D5.4).
 *
 * Lista vazia com registros ativos no sistema, ou lista que desativaria mais
 * que config('uau.limite_inativacao') dos ativos. O job falha sem retentar.
 */
class ImportacaoSuspensa extends RuntimeException
{
    /**
     * Lança a exceção se a lista recebida for suspeita.
     *
     * @param  string  $entidade  Plural, para a mensagem ("empresas", "obras").
     */
    public static function verificar(string $entidade, int $recebidos, int $ativos, int $aInativar): void
    {
        if ($ativos === 0) {
            return;
        }

        if ($recebidos === 0) {
            throw new self("Importação de {$entidade} suspensa: o UAU devolveu uma lista vazia e há {$ativos} ativas no sistema.");
        }

        $limite = (float) config('uau.limite_inativacao', 0.5);

        if ($aInativar / $ativos > $limite) {
            throw new self(sprintf(
                'Importação de %s suspensa: a lista do UAU desativaria %d de %d ativas (limite de %d%%).',
                $entidade, $aInativar, $ativos, (int) round($limite * 100),
            ));
        }
    }
}

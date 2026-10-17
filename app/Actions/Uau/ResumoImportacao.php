<?php

namespace App\Actions\Uau;

use Stringable;

/**
 * Contagem de uma importação de lista (empresas ou obras), para o log da aplicação.
 *
 * - criadas: não existiam localmente;
 * - atualizadas: já existiam ativas e algum dado mudou (só sincronizado_em não conta);
 * - reativadas: estavam inativas ou excluídas e voltaram na lista;
 * - inativadas: ativas que não vieram na lista;
 * - ignoradas: códigos que não puderam ser gravados (ex.: obra de empresa desconhecida).
 */
final readonly class ResumoImportacao implements Stringable
{
    /** @param  list<string>  $ignoradas */
    public function __construct(
        public int $criadas = 0,
        public int $atualizadas = 0,
        public int $reativadas = 0,
        public int $inativadas = 0,
        public array $ignoradas = [],
    ) {
    }

    /** Ex.: "3 criadas, 0 atualizadas, 1 inativada, 1 ignorada: 7/OB09". */
    public function __toString(): string
    {
        $partes = [
            self::contar($this->criadas, 'criada'),
            self::contar($this->atualizadas, 'atualizada'),
        ];

        if ($this->reativadas > 0) {
            $partes[] = self::contar($this->reativadas, 'reativada');
        }

        $partes[] = self::contar($this->inativadas, 'inativada');

        $texto = implode(', ', $partes);

        if ($this->ignoradas !== []) {
            $texto .= ', '.self::contar(count($this->ignoradas), 'ignorada').': '.implode(', ', $this->ignoradas);
        }

        return $texto;
    }

    private static function contar(int $quantidade, string $palavra): string
    {
        return $quantidade.' '.$palavra.($quantidade === 1 ? '' : 's');
    }
}

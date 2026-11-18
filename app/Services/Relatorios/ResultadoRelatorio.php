<?php

namespace App\Services\Relatorios;

/**
 * Colunas, linhas e total de um relatório, já formatados como texto para a tela,
 * o Excel e o PDF. `tipo` da coluna: texto, dinheiro (alinha à direita) ou numero.
 */
final class ResultadoRelatorio
{
    /**
     * @param  list<array{chave: string, rotulo: string, tipo: string}>  $colunas
     * @param  list<array<string, string|int|float|null>>  $linhas
     * @param  array<string, string|int|float|null>|null  $total
     * @param  array<string, string>  $filtrosAplicados  rótulo => valor, para o cabeçalho do PDF
     */
    public function __construct(
        public readonly array $colunas,
        public readonly array $linhas,
        public readonly ?array $total = null,
        public readonly array $filtrosAplicados = [],
    ) {
    }

    /** @return list<list<string|int|float|null>> linhas na ordem das colunas, com o total no fim. */
    public function matriz(): array
    {
        $chaves = array_column($this->colunas, 'chave');
        $matriz = array_map(fn (array $l) => array_map(fn (string $c) => $l[$c] ?? null, $chaves), $this->linhas);

        if ($this->total !== null) {
            $matriz[] = array_map(fn (string $c) => $this->total[$c] ?? null, $chaves);
        }

        return $matriz;
    }
}

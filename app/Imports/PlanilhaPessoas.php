<?php

namespace App\Imports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Lê a primeira aba da planilha de pessoas como linhas cruas. O cabeçalho e as
 * regras ficam em CarregarPlanilha e LinhaPlanilha; esta classe só lê.
 * O separador do CSV (";" ou ",") é detectado pelo PhpSpreadsheet.
 */
class PlanilhaPessoas implements ToArray
{
    /** @var list<array<int, mixed>> */
    private array $linhas = [];

    public function array(array $array): void
    {
        $this->linhas = array_values($array);
    }

    /**
     * Cabeçalho normalizado (minúsculas, sem acentos nem espaços), na ordem das colunas.
     *
     * @return list<string>
     */
    public function cabecalho(): array
    {
        return array_map(
            fn ($valor) => Str::slug((string) $valor, '_'),
            $this->linhas[0] ?? [],
        );
    }

    /**
     * Linhas de dados pelo cabeçalho, indexadas pelo número da linha na planilha
     * (o cabeçalho é a linha 1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function dados(): array
    {
        $cabecalho = $this->cabecalho();
        $dados = [];

        foreach (array_slice($this->linhas, 1) as $indice => $celulas) {
            $linha = [];
            foreach ($cabecalho as $coluna => $nome) {
                if ($nome !== '' && ! array_key_exists($nome, $linha)) {
                    $linha[$nome] = $celulas[$coluna] ?? null;
                }
            }
            $dados[$indice + 2] = $linha;
        }

        return $dados;
    }
}

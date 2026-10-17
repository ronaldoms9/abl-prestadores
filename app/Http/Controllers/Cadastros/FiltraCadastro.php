<?php

namespace App\Http\Controllers\Cadastros;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Filtros comuns das telas de consulta (fatia 7): situação (ativos por padrão)
 * e um campo de busca que entende documento com ou sem máscara (D7.2 e D7.3).
 */
trait FiltraCadastro
{
    public const SITUACOES = ['ativos' => 'Ativos', 'inativos' => 'Inativos', 'todos' => 'Todos'];

    /**
     * @param  string|null  $campoDocumento  Coluna do CPF/CNPJ (só dígitos), se houver.
     * @param  list<string>  $camposTexto  Colunas buscadas por parte do texto.
     */
    protected function filtrarCadastro(Builder $query, Request $request, ?string $campoDocumento, array $camposTexto): Builder
    {
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:100'],
            'situacao' => ['nullable', Rule::in(array_keys(self::SITUACOES))],
        ]);

        match ($filtros['situacao'] ?? 'ativos') {
            'ativos' => $query->ativos(),
            'inativos' => $query->inativos(),
            'todos' => $query,
        };

        $busca = trim($filtros['busca'] ?? '');
        if ($busca === '') {
            return $query;
        }

        // Só dígitos e sinais de máscara, com 3 dígitos ou mais: é o começo de um documento.
        $digitos = Documento::digitos($busca) ?? '';
        if ($campoDocumento !== null && preg_match('#^[\d.\-/\s]+$#', $busca) && strlen($digitos) >= 3) {
            return $query->where($query->qualifyColumn($campoDocumento), 'like', $digitos.'%');
        }

        // "!" como escape funciona igual no MySQL e no SQLite: % e _ digitados são literais.
        $termo = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca).'%';

        return $query->where(function (Builder $q) use ($camposTexto, $termo) {
            foreach ($camposTexto as $campo) {
                $q->orWhereRaw($q->qualifyColumn($campo)." like ? escape '!'", [$termo]);
            }
        });
    }

    /** A lista comum das telas de consulta (cadastros.index), com as opções de situação. */
    protected function telaDeConsulta(array $dados)
    {
        return view('cadastros.index', $dados + ['situacoes' => self::SITUACOES]);
    }

    /** Parâmetros comuns do DataTables das telas de consulta. */
    protected function parametrosTabela(array $ordem = [[1, 'asc']]): array
    {
        return [
            'searching' => false,
            'order' => $ordem,
            'language' => ['url' => url('js/datatable.json')],
            'responsive' => true,
            'pageLength' => 25,
        ];
    }
}

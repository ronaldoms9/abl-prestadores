<?php

namespace App\Support\Contratos;

use Illuminate\Support\HtmlString;

/**
 * Substitui {{variavel}} no corpo do modelo. É um renderizador próprio, nunca
 * Blade: o corpo vem do usuário e não pode executar código. Texto vira HTML
 * escapado; só HtmlString (montado por nós) entra sem escape. Variável sem
 * valor sai como um marcador visível e é listada em `faltantes`.
 */
class RenderizadorContrato
{
    private const PADRAO = '/\{\{(.*?)\}\}/s';

    /**
     * @param  array<string, string|HtmlString>  $valores
     */
    public function renderizar(string $corpoHtml, array $valores): ResultadoRenderizacao
    {
        $faltantes = [];

        $html = preg_replace_callback(self::PADRAO, function (array $achado) use ($valores, &$faltantes) {
            $chave = trim($achado[1]);

            if (! ContratoVariaveis::existe($chave)) {
                // Modelo salvo só tem variável conhecida; se escapou uma, não vira código.
                return e($achado[0]);
            }

            $valor = $valores[$chave] ?? null;

            if ($valor === null || (string) $valor === '') {
                $faltantes[$chave] = $chave;

                return '<mark>['.e($chave).' sem valor]</mark>';
            }

            return $valor instanceof HtmlString ? $valor->toHtml() : e((string) $valor);
        }, $corpoHtml);

        return new ResultadoRenderizacao($html, array_values($faltantes));
    }

    /**
     * Variáveis do corpo que o catálogo não conhece (inclui {{ }} mal formado).
     *
     * @return array<int, string>
     */
    public function desconhecidas(string $corpoHtml): array
    {
        preg_match_all(self::PADRAO, $corpoHtml, $achados);

        return array_values(array_unique(array_filter(
            array_map(fn ($conteudo) => trim(strip_tags($conteudo)) === trim($conteudo) ? trim($conteudo) : '«'.trim($conteudo).'»', $achados[1]),
            fn ($chave) => ! ContratoVariaveis::existe($chave),
        )));
    }
}

<?php

namespace App\Integrations\Uau;

/**
 * Lê as respostas do UAU nos formatos que a API usa na prática, além do
 * documentado no Swagger (lição da integração do SGTR):
 * - lista de linhas: [{...}, {...}];
 * - DataSet do .NET: [{"Tabela": [{...}]}] ou {"Tabela": [{...}]};
 * - a primeira linha de um DataSet pode ser o esquema ("System.Int32"...), que é descartada.
 */
final class RespostaUau
{
    /**
     * Linhas de dados de uma resposta de lista.
     *
     * @return list<array<string, mixed>>
     */
    public static function linhas(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $linhas = self::desembrulhar($json) ?? (array_is_list($json) ? $json : [$json]);

        return array_values(array_filter(
            $linhas,
            fn ($linha) => is_array($linha) && ! array_is_list($linha) && ! self::linhaDeEsquema($linha),
        ));
    }

    /** A primeira linha de dados, para respostas de um objeto só. */
    public static function objeto(mixed $json): ?array
    {
        return self::linhas($json)[0] ?? null;
    }

    /** Mensagem de erro do ResponseHandler do UAU ({Mensagem, Descricao, Detalhe}). */
    public static function mensagem(mixed $json): ?string
    {
        return self::campoDeErro($json, ['mensagem', 'descricao', 'message']);
    }

    /** O "Detalhe" do erro (stack trace do servidor do UAU). */
    public static function detalhe(mixed $json): ?string
    {
        return self::campoDeErro($json, ['detalhe']);
    }

    /** Valor de um campo sem diferenciar maiúsculas: a API mistura "cod_pes" e "CodPessoa". */
    public static function valor(array $linha, string $campo): mixed
    {
        if (array_key_exists($campo, $linha)) {
            return $linha[$campo];
        }

        foreach ($linha as $chave => $valor) {
            if (is_string($chave) && strcasecmp($chave, $campo) === 0) {
                return $valor;
            }
        }

        return null;
    }

    /** [{"Tabela": [...]}] ou {"Tabela": [...]} → as linhas da tabela. */
    private static function desembrulhar(array $json): ?array
    {
        $envelope = array_is_list($json) && count($json) === 1 && is_array($json[0]) ? $json[0] : $json;

        if (array_is_list($envelope) || count($envelope) !== 1) {
            return null;
        }

        $tabela = reset($envelope);

        return is_array($tabela) && array_is_list($tabela) ? $tabela : null;
    }

    /** Linha cujos valores de texto são todos nomes de tipo do .NET ("System.Int32"). */
    private static function linhaDeEsquema(array $linha): bool
    {
        $textos = array_filter($linha, 'is_string');

        return $textos !== [] && count($textos) === count(array_filter($linha, fn ($v) => $v !== null))
            && array_filter($textos, fn ($v) => ! str_starts_with($v, 'System.')) === [];
    }

    private static function campoDeErro(mixed $json, array $campos): ?string
    {
        if (is_string($json)) {
            return trim($json) !== '' ? trim($json) : null;
        }

        if (! is_array($json) || array_is_list($json)) {
            return null;
        }

        foreach ($campos as $campo) {
            $valor = self::valor($json, $campo);

            if (is_string($valor) && trim($valor) !== '') {
                return trim($valor);
            }
        }

        return null;
    }
}

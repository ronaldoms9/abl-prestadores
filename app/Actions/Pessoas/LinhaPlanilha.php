<?php

namespace App\Actions\Pessoas;

use App\Rules\Cnpj;
use App\Rules\Cpf;
use App\Support\Documento;

/**
 * Uma linha útil da planilha de pessoas, já normalizada e validada
 * (D5b.2 a D5b.4 da fatia 5b). Documento inválido fica com o texto lido,
 * cortado em 20 caracteres, e a mensagem explica o problema.
 */
final readonly class LinhaPlanilha
{
    /**
     * @param  list<string>  $erros
     */
    public function __construct(
        public int $linha,
        public ?string $cpf,
        public ?string $cnpj,
        public bool $cpfValido,
        public bool $cnpjValido,
        public array $erros = [],
    ) {
    }

    /**
     * Monta a linha a partir das células cpf e cnpj. Devolve null para a linha
     * vazia (todas as células em branco), que é ignorada.
     *
     * @param  array<string, mixed>  $celulas  Células da linha pelo cabeçalho normalizado.
     */
    public static function deCelulas(int $linha, array $celulas): ?self
    {
        if (collect($celulas)->every(fn ($valor) => self::vazio($valor))) {
            return null;
        }

        [$cpf, $erroCpf] = self::documento($celulas['cpf'] ?? null, 'CPF', 11);
        [$cnpj, $erroCnpj] = self::documento($celulas['cnpj'] ?? null, 'CNPJ', 14);
        $erros = array_values(array_filter([$erroCpf, $erroCnpj]));

        if ($cpf === null && $cnpj === null) {
            $erros[] = 'Informe o CPF ou o CNPJ.';
        }

        return new self($linha, $cpf, $cnpj, $cpf !== null && $erroCpf === null, $cnpj !== null && $erroCnpj === null, $erros);
    }

    public function mensagem(): ?string
    {
        return $this->erros === [] ? null : implode(' ', $this->erros);
    }

    /**
     * Normaliza uma célula de documento (D5b.3).
     *
     * @return array{0: ?string, 1: ?string} [documento, erro]
     */
    private static function documento(mixed $valor, string $tipo, int $tamanho): array
    {
        if (self::vazio($valor)) {
            return [null, null];
        }

        // Número do Excel: o zero à esquerda se perdeu, e o float pode vir com expoente.
        if (is_int($valor) || (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15)) {
            return self::validar(str_pad(sprintf('%.0f', $valor), $tamanho, '0', STR_PAD_LEFT), $tipo, $tamanho);
        }

        $texto = trim((string) $valor);

        if (preg_match('/^\d+([.,]\d+)?E[+-]?\d+$/i', $texto) || is_float($valor)) {
            return [mb_substr($texto, 0, 20), "{$tipo} em notação científica; formate a coluna como texto."];
        }

        if ($tipo === 'CNPJ' && preg_match('/[A-Za-z]/', $texto)) {
            return [mb_substr($texto, 0, 20), 'CNPJ com letras ainda não é aceito.'];
        }

        // Só dígitos (sem máscara) e curto: o zero à esquerda se perdeu num CSV salvo pelo Excel.
        $documento = ctype_digit($texto)
            ? str_pad($texto, $tamanho, '0', STR_PAD_LEFT)
            : (Documento::digitos($texto) ?? $texto);

        return self::validar($documento, $tipo, $tamanho, $texto);
    }

    /** @return array{0: string, 1: ?string} */
    private static function validar(string $documento, string $tipo, int $tamanho, ?string $original = null): array
    {
        $valido = $tipo === 'CPF' ? Cpf::valido($documento) : Cnpj::valido($documento);

        if ($valido) {
            return [$documento, null];
        }

        $erro = strlen($documento) === $tamanho
            ? "{$tipo} com dígito verificador inválido."
            : "{$tipo} deve ter {$tamanho} dígitos.";

        return [mb_substr($original ?? $documento, 0, 20), $erro];
    }

    private static function vazio(mixed $valor): bool
    {
        return $valor === null || (is_string($valor) && trim($valor) === '');
    }
}

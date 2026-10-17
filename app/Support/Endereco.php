<?php

namespace App\Support;

readonly class Endereco
{
    public function __construct(
        public ?string $logradouro = null,
        public ?string $numero = null,
        public ?string $complemento = null,
        public ?string $bairro = null,
        public ?string $cidade = null,
        public ?string $uf = null,
        public ?string $cep = null,
    ) {
    }

    public static function fromArray(array $dados): static
    {
        $limpar = static function ($valor): ?string {
            if ($valor === null) {
                return null;
            }
            $str = trim((string) $valor);
            return $str === '' ? null : $str;
        };

        $cep = Documento::digitos($dados['cep'] ?? null);
        $uf = $limpar($dados['uf'] ?? null);

        return new static(
            logradouro: $limpar($dados['logradouro'] ?? null),
            numero: $limpar($dados['numero'] ?? null),
            complemento: $limpar($dados['complemento'] ?? null),
            bairro: $limpar($dados['bairro'] ?? null),
            cidade: $limpar($dados['cidade'] ?? null),
            uf: $uf ? strtoupper($uf) : null,
            cep: $cep,
        );
    }

    /**
     * @return array{logradouro: ?string, numero: ?string, complemento: ?string, bairro: ?string, cidade: ?string, uf: ?string, cep: ?string}
     */
    public function toArray(): array
    {
        return [
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
        ];
    }

    public function vazio(): bool
    {
        return $this->logradouro === null
            && $this->numero === null
            && $this->complemento === null
            && $this->bairro === null
            && $this->cidade === null
            && $this->uf === null
            && $this->cep === null;
    }

    /**
     * Formata no padrão: "Rua X, 10 – Centro – Goiânia/GO – 74000-000", omitindo partes vazias.
     */
    public function formatado(): string
    {
        if ($this->vazio()) {
            return '';
        }

        $ruaNumero = array_filter([$this->logradouro, $this->numero], fn ($v) => $v !== null && $v !== '');
        $primeiraParte = $ruaNumero ? implode(', ', $ruaNumero) : null;

        $cidadeUf = match (true) {
            $this->cidade !== null && $this->uf !== null => "{$this->cidade}/{$this->uf}",
            $this->cidade !== null => $this->cidade,
            $this->uf !== null => $this->uf,
            default => null,
        };

        $cepFormatado = null;
        if ($this->cep !== null) {
            $cepFormatado = strlen($this->cep) === 8
                ? substr($this->cep, 0, 5) . '-' . substr($this->cep, 5)
                : $this->cep;
        }

        $partes = array_filter([
            $primeiraParte,
            $this->complemento,
            $this->bairro,
            $cidadeUf,
            $cepFormatado,
        ], fn ($v) => $v !== null && $v !== '');

        return implode(' – ', $partes);
    }
}

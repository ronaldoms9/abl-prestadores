<?php

namespace App\Integrations\ConsultaCnpj;

use App\Support\Documento;

/** O que a consulta de CNPJ devolve para preencher o cadastro da pessoa jurídica. */
readonly class DadosCnpj
{
    /**
     * @param  array{cep: ?string, logradouro: ?string, numero: ?string, complemento: ?string, bairro: ?string, cidade: ?string, uf: ?string}  $endereco
     */
    public function __construct(
        public string $cnpj,
        public string $razaoSocial,
        public ?string $nomeFantasia,
        public ?string $email,
        public ?string $telefone,
        public ?string $regimeTributario,
        public string $situacao,
        public array $endereco,
    ) {
    }

    public function ativa(): bool
    {
        return mb_strtoupper($this->situacao) === 'ATIVA';
    }

    /** Dado da BrasilAPI (/cnpj/v1) em campos do nosso cadastro. */
    public static function daBrasilApi(array $d): self
    {
        $texto = static function (mixed $valor): ?string {
            $valor = is_string($valor) ? trim($valor) : null;

            return $valor === '' ? null : $valor;
        };

        return new self(
            cnpj: Documento::digitos((string) ($d['cnpj'] ?? '')) ?? '',
            razaoSocial: $texto($d['razao_social'] ?? null) ?? '',
            nomeFantasia: $texto($d['nome_fantasia'] ?? null),
            email: $texto($d['email'] ?? null) ? mb_strtolower($texto($d['email'])) : null,
            telefone: Documento::digitos($texto($d['ddd_telefone_1'] ?? null) ?? ''),
            regimeTributario: self::regime($d),
            situacao: $texto($d['descricao_situacao_cadastral'] ?? null) ?? 'Não informada',
            endereco: [
                'cep' => Documento::digitos($texto($d['cep'] ?? null) ?? ''),
                'logradouro' => trim(($texto($d['descricao_tipo_de_logradouro'] ?? null) ? self::capitalizar($d['descricao_tipo_de_logradouro']).' ' : '').($texto($d['logradouro'] ?? null) ? self::capitalizar($d['logradouro']) : '')) ?: null,
                'numero' => $texto($d['numero'] ?? null),
                'complemento' => $texto($d['complemento'] ?? null),
                'bairro' => $texto($d['bairro'] ?? null) ? self::capitalizar($d['bairro']) : null,
                'cidade' => $texto($d['municipio'] ?? null) ? self::capitalizar($d['municipio']) : null,
                'uf' => $texto($d['uf'] ?? null) ? mb_strtoupper($d['uf']) : null,
            ],
        );
    }

    /** MEI e Simples vêm em campos próprios; senão vale a forma de tributação mais recente. */
    private static function regime(array $d): ?string
    {
        if (! empty($d['opcao_pelo_mei'])) {
            return 'MEI';
        }

        if (! empty($d['opcao_pelo_simples'])) {
            return 'Simples Nacional';
        }

        $historico = collect($d['regime_tributario'] ?? [])->filter(fn ($r) => is_array($r) && isset($r['ano']))->sortByDesc('ano');
        $forma = mb_strtoupper((string) ($historico->first()['forma_de_tributacao'] ?? ''));

        return match (true) {
            str_contains($forma, 'PRESUMIDO') => 'Lucro Presumido',
            str_contains($forma, 'REAL') => 'Lucro Real',
            str_contains($forma, 'SIMPLES') => 'Simples Nacional',
            default => null,
        };
    }

    private static function capitalizar(string $texto): string
    {
        return mb_convert_case(mb_strtolower(trim($texto)), MB_CASE_TITLE);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'cnpj' => $this->cnpj,
            'razao_social' => $this->razaoSocial,
            'nome_fantasia' => $this->nomeFantasia,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'regime_tributario' => $this->regimeTributario,
            'situacao' => $this->situacao,
            'ativa' => $this->ativa(),
            'endereco' => $this->endereco,
        ];
    }
}

<?php

namespace App\Integrations\Uau\Data;

use App\Support\Endereco;

/** Item de "enderecos" da pessoa. tipo: 0 principal, 1 cobrança, 2 comercial. */
final readonly class EnderecoUau
{
    use LeCamposUau;

    public const PRINCIPAL = 0;

    public function __construct(
        public ?int $tipo = null,
        public ?string $logradouro = null,
        public ?string $numero = null,
        public ?string $complemento = null,
        public ?string $bairro = null,
        public ?string $cidade = null,
        public ?string $uf = null,
        public ?string $cep = null,
    ) {
    }

    public static function fromArray(array $uau): self
    {
        return new self(
            tipo: self::inteiro($uau, 'tipoEndereco'),
            logradouro: self::texto($uau, 'endereco'),
            numero: self::texto($uau, 'numeroEndereco'),
            complemento: self::texto($uau, 'complementoEndereco'),
            bairro: self::texto($uau, 'bairro'),
            cidade: self::texto($uau, 'cidade'),
            uf: self::texto($uau, 'uf'),
            cep: self::texto($uau, 'cep'),
        );
    }

    public function toEndereco(): Endereco
    {
        return Endereco::fromArray([
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
        ]);
    }
}

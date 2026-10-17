<?php

namespace App\Integrations\Uau\Data;

/** Item de "telefones" da pessoa. tipo: 0 residencial, 1 comercial, 2 celular, 3 recado, 4 fax. */
final readonly class TelefoneUau
{
    use LeCamposUau;

    public const CELULAR = 2;

    public function __construct(
        public ?string $ddd = null,
        public ?string $numero = null,
        public ?int $tipo = null,
    ) {
    }

    public static function fromArray(array $uau): self
    {
        return new self(
            ddd: self::texto($uau, 'ddd'),
            numero: self::texto($uau, 'numero'),
            tipo: self::inteiro($uau, 'tipo'),
        );
    }

    /** DDD + número, só dígitos; null sem número. */
    public function completo(): ?string
    {
        $numero = preg_replace('/\D/', '', (string) $this->numero);

        return $numero === '' ? null : preg_replace('/\D/', '', (string) $this->ddd).$numero;
    }
}

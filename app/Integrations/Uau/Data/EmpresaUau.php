<?php

namespace App\Integrations\Uau\Data;

use App\Support\Documento;

/** Empresa de Empresa/ObterEmpresasAtivas. */
final readonly class EmpresaUau
{
    use LeCamposUau;

    public function __construct(
        public int $codigo,
        public string $razaoSocial,
        public ?string $cnpj = null,
        public ?string $inscricaoEstadual = null,
        public ?string $inscricaoMunicipal = null,
        public ?string $endereco = null,
        public ?string $telefone = null,
    ) {
    }

    public static function fromArray(array $uau): self
    {
        $cnpj = Documento::digitos(self::texto($uau, 'CGC_emp'));

        return new self(
            codigo: self::obrigatorio(self::inteiro($uau, 'Codigo_emp'), 'Codigo_emp'),
            razaoSocial: self::texto($uau, 'Desc_emp') ?? '',
            cnpj: $cnpj === null ? null : str_pad($cnpj, 14, '0', STR_PAD_LEFT),
            inscricaoEstadual: self::texto($uau, 'IE_emp'),
            inscricaoMunicipal: self::texto($uau, 'InscrMunic_emp'),
            endereco: self::texto($uau, 'Endereco_emp'),
            telefone: self::texto($uau, 'Fone_emp'),
        );
    }
}

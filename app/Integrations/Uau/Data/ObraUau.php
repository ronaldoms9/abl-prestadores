<?php

namespace App\Integrations\Uau\Data;

use Carbon\CarbonImmutable;

/** Obra de Obras/ObterObrasAtivas. A chave no UAU é (empresa, código). */
final readonly class ObraUau
{
    use LeCamposUau;

    public function __construct(
        public string $codigo,
        public int $empresaCodigo,
        public string $descricao,
        public ?int $status = null,
        public ?int $tipo = null,
        public ?string $endereco = null,
        public ?CarbonImmutable $dataInicio = null,
        public ?CarbonImmutable $dataFim = null,
        public ?CarbonImmutable $alteradaEm = null,
    ) {
    }

    public static function fromArray(array $uau): self
    {
        return new self(
            codigo: self::obrigatorio(self::texto($uau, 'Cod_obr'), 'Cod_obr'),
            empresaCodigo: self::obrigatorio(self::inteiro($uau, 'Empresa_obr'), 'Empresa_obr'),
            descricao: self::texto($uau, 'Descr_obr') ?? '',
            status: self::inteiro($uau, 'Status_obr'),
            tipo: self::inteiro($uau, 'TipoObra_obr'),
            endereco: self::texto($uau, 'Ender_obr'),
            dataInicio: self::data($uau, 'DtIni_obr'),
            dataFim: self::data($uau, 'Dtfim_obr'),
            alteradaEm: self::data($uau, 'DataAlt_obr'),
        );
    }
}

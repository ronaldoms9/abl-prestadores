<?php

namespace App\Integrations\Uau\Mappers;

use App\Integrations\Uau\Data\ObraUau;
use App\Models\Empresa;
use App\Support\Endereco;

/**
 * ObraUau → atributos de obras (D5.6 da fatia 5).
 *
 * Recebe a empresa local já resolvida pela Action; não acessa o banco.
 */
final class ObraMapper
{
    /** @return array<string, mixed> */
    public static function atributos(ObraUau $obra, Empresa $empresa): array
    {
        return [
            'empresa_id' => $empresa->getKey(),
            'uau_codigo' => $obra->codigo,
            'descricao' => $obra->descricao,
            'status_uau' => $obra->status,
            'tipo_uau' => $obra->tipo === null ? null : (string) $obra->tipo,
            'data_inicio' => $obra->dataInicio?->toDateString(),
            'data_fim' => $obra->dataFim?->toDateString(),
            'endereco' => $obra->endereco === null ? null : new Endereco(logradouro: $obra->endereco),
        ];
    }
}

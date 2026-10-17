<?php

namespace App\Integrations\Uau\Mappers;

use App\Integrations\Uau\Data\EmpresaUau;
use App\Support\Endereco;

/**
 * EmpresaUau → atributos de empresas (D5.6 da fatia 5).
 *
 * Não acessa o banco. Campo vazio no UAU grava null: o UAU é a fonte do
 * cadastro na E1. ativo e sincronizado_em ficam com a Action.
 */
final class EmpresaMapper
{
    /** @return array<string, mixed> */
    public static function atributos(EmpresaUau $empresa): array
    {
        return [
            'uau_codigo' => $empresa->codigo,
            'cnpj' => $empresa->cnpj,
            'razao_social' => $empresa->razaoSocial,
            'inscricao_estadual' => $empresa->inscricaoEstadual,
            'inscricao_municipal' => $empresa->inscricaoMunicipal,
            'telefone' => $empresa->telefone,
            // O UAU manda o endereço da empresa em texto único.
            'endereco' => $empresa->endereco === null ? null : new Endereco(logradouro: $empresa->endereco),
        ];
    }
}

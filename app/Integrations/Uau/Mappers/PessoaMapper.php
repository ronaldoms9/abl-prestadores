<?php

namespace App\Integrations\Uau\Mappers;

use App\Integrations\Uau\Data\PessoaUau;

/**
 * PessoaUau → atributos de pessoas_fisicas ou pessoas_juridicas (D5.6 da fatia 5).
 *
 * Não acessa o banco. regime_tributario não é tocado: é da E2.
 */
final class PessoaMapper
{
    /** @return array<string, mixed> */
    public static function fisica(PessoaUau $pessoa): array
    {
        return [
            'uau_codigo' => $pessoa->codigo,
            'cpf' => $pessoa->documento,
            'nome' => $pessoa->nome,
            'nascimento' => $pessoa->nascimento?->toDateString(),
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefonePrincipal(),
            'endereco' => $pessoa->enderecoPrincipal()?->toEndereco(),
            'ativo' => $pessoa->ativa,
        ];
    }

    /** @return array<string, mixed> */
    public static function juridica(PessoaUau $pessoa): array
    {
        return [
            'uau_codigo' => $pessoa->codigo,
            'cnpj' => $pessoa->documento,
            'razao_social' => $pessoa->nome,
            'nome_fantasia' => $pessoa->nomeFantasia,
            'inscricao_municipal' => $pessoa->inscricaoMunicipal,
            'inscricao_estadual' => $pessoa->inscricaoEstadual,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefonePrincipal(),
            'endereco' => $pessoa->enderecoPrincipal()?->toEndereco(),
            'ativo' => $pessoa->ativa,
        ];
    }
}

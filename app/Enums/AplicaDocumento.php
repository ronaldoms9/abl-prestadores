<?php

namespace App\Enums;

enum AplicaDocumento: string
{
    case PessoaFisica = 'pessoa_fisica';
    case PessoaJuridica = 'pessoa_juridica';
    case Colaborador = 'colaborador';
    case Todos = 'todos';

    public function rotulo(): string
    {
        return match ($this) {
            self::PessoaFisica => 'Pessoa física',
            self::PessoaJuridica => 'Pessoa jurídica',
            self::Colaborador => 'Colaborador',
            self::Todos => 'Todos',
        };
    }

    /** Apelido de morph do dono do documento (pessoa-fisica, pessoa-juridica, colaborador). */
    public function aceita(string $apelido): bool
    {
        return match ($this) {
            self::Todos => true,
            self::PessoaFisica => $apelido === 'pessoa-fisica',
            self::PessoaJuridica => $apelido === 'pessoa-juridica',
            self::Colaborador => $apelido === 'colaborador',
        };
    }
}

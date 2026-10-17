<?php

namespace App\Integrations\Uau;

/** Operações feitas no UAU, gravadas em integracao_logs.operacao. */
enum OperacaoUau: string
{
    case Autenticar = 'autenticar';
    case Versao = 'versao';
    case EmpresasAtivas = 'empresas-ativas';
    case ObrasAtivas = 'obras-ativas';
    case PessoaPorDocumento = 'pessoa-por-documento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Autenticar => 'Autenticação',
            self::Versao => 'Versão do UAU',
            self::EmpresasAtivas => 'Empresas ativas',
            self::ObrasAtivas => 'Obras ativas',
            self::PessoaPorDocumento => 'Pessoa por CPF/CNPJ',
        };
    }
}

<?php

namespace App\Enums;

enum CategoriaQualificacao: string
{
    case Curso = 'curso';
    case Certificacao = 'certificacao';
    case TreinamentoSeguranca = 'treinamento_seguranca';
    case Habilitacao = 'habilitacao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Curso => 'Curso',
            self::Certificacao => 'Certificação',
            self::TreinamentoSeguranca => 'Treinamento de segurança',
            self::Habilitacao => 'Habilitação',
        };
    }
}

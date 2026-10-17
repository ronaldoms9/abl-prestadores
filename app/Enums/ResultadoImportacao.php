<?php

namespace App\Enums;

enum ResultadoImportacao: string
{
    case Importada = 'importada';
    case JaExistia = 'ja_existia';
    case NaoEncontrada = 'nao_encontrada';
    case Inativa = 'inativa';
    case Invalida = 'invalida';

    public function rotulo(): string
    {
        return match ($this) {
            self::Importada => 'Importada',
            self::JaExistia => 'Já existia',
            self::NaoEncontrada => 'Não encontrada',
            self::Inativa => 'Inativa',
            self::Invalida => 'Inválida',
        };
    }
}

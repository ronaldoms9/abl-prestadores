<?php

namespace App\Enums;

enum ResultadoImportacao: string
{
    case Importada = 'importada';
    case JaExistia = 'ja_existia';
    case NaoEncontrada = 'nao_encontrada';
    case Inativa = 'inativa';
    case Invalida = 'invalida';
    case Recusada = 'recusada';

    /** Resultados em que a pessoa existe localmente depois da consulta. */
    public function temPessoa(): bool
    {
        return in_array($this, [self::Importada, self::JaExistia, self::Inativa], true);
    }

    /** Resultados que pedem atenção no relatório da carga. */
    public function comProblema(): bool
    {
        return in_array($this, [self::NaoEncontrada, self::Invalida, self::Recusada], true);
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Importada => 'Importada',
            self::JaExistia => 'Já existia',
            self::NaoEncontrada => 'Não encontrada',
            self::Inativa => 'Inativa',
            self::Invalida => 'Inválida',
            self::Recusada => 'Recusada pelo UAU',
        };
    }
}

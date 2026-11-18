<?php

namespace App\Enums;

enum OrigemDadosNota: string
{
    case Xml = 'xml';
    case Manual = 'manual';

    public function rotulo(): string
    {
        return $this === self::Xml ? 'Lida do XML' : 'Digitada';
    }
}

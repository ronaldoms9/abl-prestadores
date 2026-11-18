<?php

namespace App\Enums;

enum StatusNota: string
{
    /** Sem nota enviada ainda (só aparece nas telas; não existe linha com este status). */
    case Aguardando = 'aguardando';
    case Enviada = 'enviada';
    case Divergente = 'divergente';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aguardando => 'Aguardando nota',
            self::Enviada => 'Enviada',
            self::Divergente => 'Divergente',
            self::Aprovada => 'Aprovada',
            self::Recusada => 'Recusada',
        };
    }

    /** O prestador pode enviar (ou reenviar) a nota neste estado. */
    public function aceitaEnvio(): bool
    {
        return in_array($this, [self::Aguardando, self::Recusada], true);
    }

    /** O financeiro ainda pode decidir. */
    public function aguardaDecisao(): bool
    {
        return in_array($this, [self::Enviada, self::Divergente], true);
    }
}

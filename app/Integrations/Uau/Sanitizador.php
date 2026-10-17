<?php

namespace App\Integrations\Uau;

/** Troca segredos e dados bancários por "[oculto]" antes de gravar o log. */
final class Sanitizador
{
    public const OCULTO = '[oculto]';

    /** @var list<string> */
    private array $campos;

    /** @param  list<string>  $campos  Nomes de campo, sem diferenciar maiúsculas. */
    public function __construct(array $campos)
    {
        $this->campos = array_map('mb_strtolower', $campos);
    }

    public function limpar(mixed $dados): mixed
    {
        if (! is_array($dados)) {
            return $dados;
        }

        foreach ($dados as $chave => $valor) {
            if (is_string($chave) && in_array(mb_strtolower($chave), $this->campos, true)) {
                $dados[$chave] = self::OCULTO;
            } elseif (is_array($valor)) {
                $dados[$chave] = $this->limpar($valor);
            }
        }

        return $dados;
    }
}

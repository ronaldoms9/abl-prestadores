<?php

namespace App\Support\Contratos;

/** O HTML pronto e as variáveis que ficaram sem valor (o contrato não deve sair com elas). */
readonly class ResultadoRenderizacao
{
    /** @param  array<int, string>  $faltantes */
    public function __construct(public string $html, public array $faltantes)
    {
    }

    public function completo(): bool
    {
        return $this->faltantes === [];
    }
}

<?php

namespace App\Integrations\Uau\Exceptions;

use RuntimeException;
use Throwable;

/** Falha numa chamada ao UAU. Carrega o status HTTP e a linha de integracao_logs. */
abstract class UauException extends RuntimeException
{
    public function __construct(
        string $mensagem,
        public readonly ?int $httpStatus = null,
        public readonly ?int $logId = null,
        ?Throwable $anterior = null,
    ) {
        parent::__construct($mensagem, 0, $anterior);
    }
}

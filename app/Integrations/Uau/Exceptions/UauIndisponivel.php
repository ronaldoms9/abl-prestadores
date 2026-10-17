<?php

namespace App\Integrations\Uau\Exceptions;

/** Rede, timeout, 5xx ou 429: o job deve tentar de novo. */
class UauIndisponivel extends UauException
{
}

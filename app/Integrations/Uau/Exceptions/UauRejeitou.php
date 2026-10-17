<?php

namespace App\Integrations\Uau\Exceptions;

/** O UAU recusou o pedido (4xx) ou respondeu fora do formato: tentar de novo não resolve. */
class UauRejeitou extends UauException
{
}

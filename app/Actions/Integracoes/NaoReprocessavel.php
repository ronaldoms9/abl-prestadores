<?php

namespace App\Actions\Integracoes;

use RuntimeException;

/** Chamada que não pode ser reprocessada; a mensagem diz o motivo (D8.4 da fatia 8). */
class NaoReprocessavel extends RuntimeException
{
}

<?php

namespace App\Services\NotaFiscal;

use RuntimeException;

/** O XML não pôde ser lido (inválido, perigoso ou de layout desconhecido). A mensagem é para o usuário. */
class XmlNaoLido extends RuntimeException
{
}

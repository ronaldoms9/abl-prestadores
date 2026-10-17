<?php

namespace App\Integrations\Uau;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Uma requisição HTTP ao UAU e o seu resultado, antes de ir para o log.
 *
 * @internal Uso exclusivo do UauClient.
 */
final readonly class Tentativa
{
    public function __construct(
        public OperacaoUau $operacao,
        public string $rota,
        public ?array $corpo,
        public ?Response $resposta,
        public CarbonImmutable $inicio,
        public int $duracaoMs,
        public string $correlacao,
    ) {
    }
}

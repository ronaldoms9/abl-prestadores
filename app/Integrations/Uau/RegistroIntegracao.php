<?php

namespace App\Integrations\Uau;

use App\Enums\DirecaoIntegracao;
use App\Enums\StatusIntegracao;
use App\Models\IntegracaoLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava uma linha de integracao_logs por chamada ao UAU, sem segredos.
 *
 * Gravar o log nunca derruba a chamada: uma falha aqui vai para report() e a
 * chamada segue. A linha é gravada fora de transação; quem chama o UAU não deve
 * fazê-lo dentro de DB::transaction, senão o rollback apaga o log da falha.
 */
class RegistroIntegracao
{
    private const LIMITE_TEXTO = 2000;

    public function __construct(private readonly Sanitizador $sanitizador)
    {
    }

    /**
     * @param  array<string, mixed>|null  $payload  Corpo enviado.
     * @param  mixed  $resposta  Corpo recebido: array, texto (JSON ou não) ou escalar.
     */
    public function registrar(
        OperacaoUau $operacao,
        string $rota,
        ContextoChamada $contexto,
        ?array $payload,
        mixed $resposta,
        ?int $httpStatus,
        StatusIntegracao $status,
        ?string $erro,
        CarbonImmutable $executadoEm,
        int $duracaoMs,
        ?string $correlacao = null,
    ): ?IntegracaoLog {
        try {
            return IntegracaoLog::create([
                'direcao' => DirecaoIntegracao::Entrada,
                'entidade_type' => $contexto->entidade?->getMorphClass(),
                'entidade_id' => $contexto->entidade?->getKey(),
                'operacao' => $operacao->value,
                'rota' => $rota,
                'chave_idempotencia' => $contexto->chaveIdempotencia,
                'correlacao' => $correlacao,
                'payload' => $payload === null ? null : $this->sanitizador->limpar($payload),
                'resposta' => $this->resposta($resposta),
                'http_status' => $httpStatus,
                'status' => $status,
                'tentativas' => $contexto->tentativa,
                'erro' => $erro === null ? null : Str::limit($erro, self::LIMITE_TEXTO),
                'duracao_ms' => $duracaoMs,
                'executado_em' => $executadoEm,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Texto JSON é decodificado; texto que não é JSON vai como {"bruto": ...}; escalar como {"valor": ...}. */
    private function resposta(mixed $resposta): ?array
    {
        if ($resposta === null || $resposta === '') {
            return null;
        }

        if (is_string($resposta)) {
            $json = json_decode($resposta, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['bruto' => Str::limit($resposta, self::LIMITE_TEXTO)];
            }

            $resposta = $json;
        }

        return $this->sanitizador->limpar(is_array($resposta) ? $resposta : ['valor' => $resposta]);
    }
}

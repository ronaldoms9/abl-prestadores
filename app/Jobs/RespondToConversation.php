<?php

namespace App\Jobs;

use App\Bot\BotReply;
use App\Bot\BotReplyInvalida;
use App\Bot\Handoff;
use App\Bot\MessageSender;
use App\Bot\PriceGuard;
use App\Bot\PromptBuilder;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetricDaily;
use App\Models\Tenant;
use App\OpenRouter\ChatResult;
use App\OpenRouter\OpenRouterClient;
use App\OpenRouter\OpenRouterException;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RespondToConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly string $debounceToken,
    ) {
        $this->onQueue(config('whatsapp.fila'));
    }

    public function handle(
        TenantContext $contexto,
        PromptBuilder $prompt,
        OpenRouterClient $llm,
        PriceGuard $guarda,
        MessageSender $sender,
        Handoff $handoff,
    ): void {
        $contexto->setById($this->tenantId);

        $conversa = Conversation::find($this->conversationId);

        if ($conversa === null) {
            return;
        }

        // Mensagem nova cancela a geração em curso — sem depender de cancelamento de job.
        if (! $conversa->tokenAindaVale($this->debounceToken)) {
            return;
        }

        if (! $conversa->botEstaNoComando()) {
            return;
        }

        $tenant = Tenant::findOrFail($this->tenantId);

        if (! $tenant->estaAtivo() || $tenant->atingiuTeto()) {
            return;
        }

        $config = $tenant->botConfig;

        if ($config === null) {
            $handoff->escalar($tenant, $conversa, 'O bot ainda não foi configurado para este negócio.');

            return;
        }

        $ultima = $conversa->messages()
            ->where('direcao', Message::ENTRADA)
            ->latest('id')
            ->first();

        $texto = $ultima?->textoUtil();

        if ($texto === null || trim($texto) === '') {
            return;
        }

        if ($config->estaForaDeHorario()) {
            MetricDaily::registrar($tenant->id, ['fora_horario' => 1]);
        }

        try {
            $resultado = $llm->chat(
                $prompt->build($config, $conversa, $texto),
                config('openrouter.modelos'),
                (int) config('openrouter.timeout'),
            );

            $resposta = BotReply::deJson($resultado->conteudo);
        } catch (OpenRouterException|BotReplyInvalida $e) {
            Log::error('Geração falhou; escalando para humano.', [
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversa->id,
                'erro' => $e->getMessage(),
            ]);

            $handoff->escalar(
                $tenant,
                $conversa,
                motivo: 'Falha ao gerar resposta: '.$e->getMessage(),
                mensagemAoCliente: config('whatsapp.mensagem_escape'),
                ultimaMensagemDoCliente: $texto,
            );

            return;
        }

        // O custo existe mesmo quando a resposta é barrada: métrica sempre entra.
        $this->registrarUso($tenant, $resultado);

        // Trava 1: preço que não está na lista de produtos não sai daqui.
        $veredito = $guarda->verificar($resposta->resposta, $config);

        if (! $veredito->liberado) {
            Log::warning('Resposta bloqueada pelo PriceGuard.', [
                'conversation_id' => $conversa->id,
                'inventados' => $veredito->inventados,
            ]);

            $handoff->escalar(
                $tenant,
                $conversa,
                motivo: $veredito->motivo(),
                mensagemAoCliente: config('whatsapp.mensagem_escape'),
                ultimaMensagemDoCliente: $texto,
            );

            return;
        }

        // Trava 2: confiança baixa ou pedido explícito de humano.
        if (! $resposta->confiavel((float) config('whatsapp.handoff.confianca_minima'))) {
            $handoff->escalar(
                $tenant,
                $conversa,
                motivo: $resposta->motivo ?? sprintf('Confiança de %.2f abaixo do limiar.', $resposta->confianca),
                mensagemAoCliente: config('whatsapp.mensagem_escape'),
                ultimaMensagemDoCliente: $texto,
            );

            return;
        }

        $sender->enviar($tenant, $conversa, $resposta->resposta);

        // Só entra no histórico o que o cliente realmente recebeu.
        Message::create([
            'conversation_id' => $conversa->id,
            'tenant_id' => $tenant->id,
            'direcao' => Message::SAIDA,
            'tipo' => 'texto',
            'conteudo' => $resposta->resposta,
            'tokens_entrada' => $resultado->tokensEntrada,
            'tokens_saida' => $resultado->tokensSaida,
            'custo_usd' => $resultado->custoUsd,
            'modelo' => $resultado->modelo,
        ]);

        MetricDaily::registrar($tenant->id, ['resolvidas' => 1]);
    }

    private function registrarUso(Tenant $tenant, ChatResult $resultado): void
    {
        $tenant->increment('mensagens_mes');

        MetricDaily::registrar($tenant->id, [
            'tokens_entrada' => $resultado->tokensEntrada,
            'tokens_saida' => $resultado->tokensSaida,
            'custo_usd' => $resultado->custoUsd,
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Bot\Handoff;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetricDaily;
use App\Models\Tenant;
use App\OpenRouter\OpenRouterClient;
use App\OpenRouter\OpenRouterException;
use App\Tenancy\TenantContext;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Data\IncomingMessage;
use App\WhatsApp\Enums\MessageKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $tenantId,
        public readonly IncomingMessage $mensagem,
    ) {
        $this->onQueue(config('whatsapp.fila'));
    }

    public function handle(
        TenantContext $contexto,
        WhatsAppProvider $provider,
        OpenRouterClient $llm,
        Handoff $handoff,
    ): void {
        // Primeira linha, sempre: sem isso um job de um cliente enxerga dados de outro.
        $contexto->setById($this->tenantId);

        $tenant = Tenant::findOrFail($this->tenantId);

        if (! $tenant->estaAtivo()) {
            Log::info('Mensagem ignorada: assinatura vencida.', ['tenant_id' => $tenant->id]);

            return;
        }

        if ($tenant->atingiuTeto()) {
            Log::warning('Mensagem ignorada: teto de mensagens do mês atingido.', ['tenant_id' => $tenant->id]);

            return;
        }

        $transcricao = null;
        $falhaNoAudio = false;

        if ($this->mensagem->kind === MessageKind::Audio && $this->mensagem->media !== null) {
            // O arquivo local do WAHA expira em 180 segundos: baixar agora, não
            // depois do debounce, ou o áudio some.
            try {
                $caminho = $provider->downloadMedia($this->mensagem->media->url);
                $transcricao = $llm->transcribe($caminho);
                @unlink($caminho);
            } catch (OpenRouterException|\RuntimeException $e) {
                $falhaNoAudio = true;
                Log::warning('Falha ao transcrever áudio.', [
                    'tenant_id' => $tenant->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        [$conversa, $nova] = $this->conversa($tenant);

        Message::create([
            'conversation_id' => $conversa->id,
            'tenant_id' => $tenant->id,
            'direcao' => Message::ENTRADA,
            'tipo' => $this->mensagem->kind->value,
            'waha_message_id' => $this->mensagem->messageId,
            'conteudo' => $this->mensagem->texto,
            'transcricao' => $transcricao,
        ]);

        if ($nova) {
            MetricDaily::registrar($tenant->id, ['conversas' => 1]);
        }

        if ($falhaNoAudio) {
            $handoff->escalar(
                $tenant,
                $conversa,
                motivo: 'Não foi possível transcrever o áudio do cliente.',
                mensagemAoCliente: config('whatsapp.mensagem_escape'),
            );

            return;
        }

        // Imagem, documento, localização e contato não são descartados: viram
        // registro e vão direto para humano.
        if ($this->mensagem->kind->exigeHumano()) {
            $handoff->escalar(
                $tenant,
                $conversa,
                motivo: 'O cliente mandou '.$this->mensagem->kind->value.', que o bot não trata.',
                mensagemAoCliente: config('whatsapp.mensagem_escape'),
            );

            return;
        }

        if (! $conversa->botEstaNoComando()) {
            Log::info('Conversa está com humano: o bot não responde.', ['conversation_id' => $conversa->id]);

            return;
        }

        // Token novo cancela a geração em curso; a resposta só sai depois da rajada.
        $token = $conversa->novoDebounceToken();

        RespondToConversation::dispatch($tenant->id, $conversa->id, $token)
            ->delay(now()->addSeconds((int) config('whatsapp.debounce_segundos')));
    }

    /** @return array{0: Conversation, 1: bool} */
    private function conversa(Tenant $tenant): array
    {
        $conversa = Conversation::firstOrNew([
            'tenant_id' => $tenant->id,
            'chat_id' => $this->mensagem->chatId,
        ]);

        $nova = ! $conversa->exists;

        $conversa->fill([
            'ultima_mensagem_at' => now(),
            'expurgo_em' => now()->addDays((int) config('whatsapp.expurgo_dias')),
        ]);

        if ($nova) {
            $conversa->estado = Conversation::ESTADO_BOT;
        }

        $conversa->save();

        return [$conversa, $nova];
    }
}

<?php

namespace Tests\Feature\Bot;

use App\Jobs\ProcessIncomingMessage;
use App\Models\BotConfig;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\WhatsApp\Data\IncomingMessage;
use App\WhatsApp\Enums\MessageKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IsolamentoEntreTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_de_um_tenant_nao_enxerga_conversa_de_outro(): void
    {
        Queue::fake();

        $a = Tenant::factory()->create(['waha_session' => 'a']);
        $b = Tenant::factory()->create(['waha_session' => 'b']);
        BotConfig::factory()->create(['tenant_id' => $a->id]);
        BotConfig::factory()->create(['tenant_id' => $b->id]);

        // Mesmo número falando com dois negócios diferentes.
        $chat = '5511999999999@c.us';

        Conversation::factory()->create(['tenant_id' => $b->id, 'chat_id' => $chat]);

        $job = new ProcessIncomingMessage($a->id, new IncomingMessage(
            session: 'a',
            chatId: $chat,
            messageId: 'msg-1',
            kind: MessageKind::Texto,
            texto: 'oi',
            media: null,
            fromMe: false,
            timestamp: CarbonImmutable::now(),
        ));

        $this->app->call([$job, 'handle']);

        // O contexto do job ficou no tenant A, então a consulta abaixo é do A.
        $this->assertSame($a->id, app(TenantContext::class)->id());
        $this->assertSame(1, Conversation::count());
        $this->assertSame($a->id, Conversation::first()->tenant_id);

        // Sem escopo: as duas conversas existem, uma de cada tenant.
        $this->assertSame(2, Conversation::query()->semEscopoDeTenant()->count());
        $this->assertSame($a->id, Message::first()->tenant_id);
    }
}

<?php

namespace Tests\Feature\Bot;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetricDaily;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpurgoTest extends TestCase
{
    use RefreshDatabase;

    public function test_apaga_conversas_vencidas_e_preserva_metricas(): void
    {
        $tenant = Tenant::factory()->create();

        $vencida = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'expurgo_em' => now()->subDay(),
        ]);
        Message::factory()->count(3)->create([
            'conversation_id' => $vencida->id,
            'tenant_id' => $tenant->id,
        ]);

        $recente = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'expurgo_em' => now()->addDays(30),
        ]);
        Message::factory()->create([
            'conversation_id' => $recente->id,
            'tenant_id' => $tenant->id,
        ]);

        MetricDaily::registrar($tenant->id, ['conversas' => 2, 'custo_usd' => 0.005]);

        $this->artisan('conversas:expurgar')->assertSuccessful();

        $this->assertDatabaseMissing('conversations', ['id' => $vencida->id]);
        $this->assertDatabaseHas('conversations', ['id' => $recente->id]);
        $this->assertSame(1, Message::count());

        // Métrica agregada não é dado pessoal: sobrevive ao expurgo.
        $this->assertSame(2, MetricDaily::first()->conversas);
    }

    public function test_dry_run_nao_apaga_nada(): void
    {
        $tenant = Tenant::factory()->create();

        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'expurgo_em' => now()->subDay(),
        ]);

        $this->artisan('conversas:expurgar --dry-run')->assertSuccessful();

        $this->assertSame(1, Conversation::count());
    }

    public function test_expurga_conversas_de_todos_os_tenants(): void
    {
        foreach (Tenant::factory()->count(3)->create() as $tenant) {
            Conversation::factory()->create([
                'tenant_id' => $tenant->id,
                'expurgo_em' => now()->subDay(),
            ]);
        }

        $this->artisan('conversas:expurgar')->assertSuccessful();

        $this->assertSame(0, Conversation::count());
    }
}

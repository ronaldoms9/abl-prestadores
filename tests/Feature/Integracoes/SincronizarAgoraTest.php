<?php

namespace Tests\Feature\Integracoes;

use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ConcluirSincronizacaoJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Models\Sincronizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SincronizarAgoraTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        $this->usuario = $this->usuarioCom('integracao-list', 'integracao-sincronizar');
    }

    public function test_botao_dispara_a_cadeia_e_registra_a_auditoria(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('integracao.sincronizar'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $sincronizacao = Sincronizacao::sole();
        $this->assertSame(OrigemSincronizacao::Manual, $sincronizacao->origem);
        $this->assertSame($this->usuario->id, $sincronizacao->created_by);
        Bus::assertChained([ImportarEmpresasJob::class, ImportarObrasJob::class, ConciliarPessoasJob::class, ConcluirSincronizacaoJob::class]);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'sincronizacao-solicitada',
            'subject_type' => 'sincronizacao',
            'subject_id' => $sincronizacao->id,
            'causer_id' => $this->usuario->id,
        ]);
    }

    public function test_segundo_pedido_com_a_primeira_em_andamento_e_recusado(): void
    {
        $this->actingAs($this->usuario)->postJson(route('integracao.sincronizar'))->assertOk();

        $this->actingAs($this->usuario)
            ->postJson(route('integracao.sincronizar'))
            ->assertStatus(409)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn ($mensagem) => str_starts_with($mensagem, 'Já há uma sincronização em andamento'));

        $this->assertSame(1, Sincronizacao::count());
        $this->assertSame(1, Activity::where('event', 'sincronizacao-solicitada')->count());
    }

    public function test_quadro_mostra_as_ultimas_e_o_botao_so_com_a_permissao(): void
    {
        $autor = User::factory()->create(['name' => 'Ana Financeiro']);
        Sincronizacao::factory()->create(['origem' => OrigemSincronizacao::Manual, 'created_by' => $autor->id]);
        Sincronizacao::factory()->create(['status' => StatusSincronizacao::Falhou, 'erro' => 'Resposta fora do padrão.']);

        $this->actingAs($this->usuario)->get(route('integracao.index'))
            ->assertOk()
            ->assertSee('Ana Financeiro')
            ->assertSee('Resposta fora do padrão.')
            ->assertSee('Todo dia às 05:00')
            ->assertSee('id="sincronizar-agora"', false)
            ->assertSee('Sincronizar agora');

        $this->actingAs($this->usuarioCom('integracao-list'))->get(route('integracao.index'))
            ->assertOk()
            ->assertSee('Ana Financeiro')
            ->assertDontSee('id="sincronizar-agora"', false);
    }

    public function test_botao_desabilitado_com_uma_em_andamento(): void
    {
        Sincronizacao::factory()->emAndamento()->create();

        $this->actingAs($this->usuario)->get(route('integracao.index'))
            ->assertSee('Sincronização em andamento')
            ->assertSee('disabled', false);
    }
}

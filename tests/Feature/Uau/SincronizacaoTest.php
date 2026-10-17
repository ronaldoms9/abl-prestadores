<?php

namespace Tests\Feature\Uau;

use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Integrations\Uau\Conciliacao;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ConcluirSincronizacaoJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Integrations\Uau\OperacaoUau;
use App\Integrations\Uau\SincronizacaoEmAndamento;
use App\Models\Empresa;
use App\Models\Obra;
use App\Models\Sincronizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class SincronizacaoTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    public function test_cadeia_completa_conclui_a_sincronizacao(): void
    {
        $this->simularUau();
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $sincronizacao = Conciliacao::disparar(OrigemSincronizacao::Manual)->fresh();

        $this->assertSame(StatusSincronizacao::Concluida, $sincronizacao->status);
        $this->assertSame(OrigemSincronizacao::Manual, $sincronizacao->origem);
        $this->assertNotNull($sincronizacao->concluida_em);
        $this->assertSame($usuario->id, $sincronizacao->created_by);
        $this->assertSame(3, Empresa::count());
        $this->assertTrue($sincronizacao->is(Sincronizacao::ultimaConcluida()));
    }

    public function test_falha_de_um_elo_marca_falhou_e_interrompe_a_cadeia(): void
    {
        $this->simularUau()->falharEm(OperacaoUau::EmpresasAtivas, new UauRejeitou('Resposta fora do padrão.', 400));

        Conciliacao::disparar(OrigemSincronizacao::Agendada);

        $sincronizacao = Sincronizacao::sole();
        $this->assertSame(StatusSincronizacao::Falhou, $sincronizacao->status);
        $this->assertSame('Resposta fora do padrão.', $sincronizacao->erro);
        $this->assertSame(0, Obra::count());
        $this->assertNull(Sincronizacao::ultimaConcluida());
    }

    public function test_segunda_com_outra_em_andamento_e_recusada(): void
    {
        Bus::fake();
        Sincronizacao::factory()->emAndamento()->create(['iniciada_em' => now()->subMinutes(10)]);

        $this->expectException(SincronizacaoEmAndamento::class);

        try {
            Conciliacao::disparar(OrigemSincronizacao::Manual);
        } finally {
            Bus::assertNothingDispatched();
            $this->assertSame(1, Sincronizacao::count());
        }
    }

    public function test_em_andamento_ha_mais_de_uma_hora_e_dada_como_interrompida(): void
    {
        Bus::fake();
        $antiga = Sincronizacao::factory()->emAndamento()->create(['iniciada_em' => now()->subMinutes(61)]);

        $nova = Conciliacao::disparar(OrigemSincronizacao::Agendada);

        $this->assertSame(StatusSincronizacao::Falhou, $antiga->fresh()->status);
        $this->assertSame('Interrompida: o worker parou antes de concluir.', $antiga->fresh()->erro);
        $this->assertSame(StatusSincronizacao::EmAndamento, $nova->status);
        Bus::assertChained([
            ImportarEmpresasJob::class,
            ImportarObrasJob::class,
            ConciliarPessoasJob::class,
            ConcluirSincronizacaoJob::class,
        ]);
    }

    public function test_concluir_nao_mexe_em_sincronizacao_ja_encerrada(): void
    {
        $falhou = Sincronizacao::factory()->create(['status' => StatusSincronizacao::Falhou, 'erro' => 'x']);

        (new ConcluirSincronizacaoJob($falhou->id))->handle();

        $this->assertSame(StatusSincronizacao::Falhou, $falhou->fresh()->status);
    }
}

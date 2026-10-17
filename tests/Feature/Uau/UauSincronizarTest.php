<?php

namespace Tests\Feature\Uau;

use App\Enums\OrigemSincronizacao;
use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ConcluirSincronizacaoJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Models\Sincronizacao;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class UauSincronizarTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_registra_uma_sincronizacao_agendada_e_despacha_a_cadeia(): void
    {
        Bus::fake();

        $this->artisan('uau:sincronizar')
            ->expectsOutputToContain('enviada para a fila uau')
            ->assertSuccessful();

        $this->assertSame(OrigemSincronizacao::Agendada, Sincronizacao::sole()->origem);
        Bus::assertChained([ImportarEmpresasJob::class, ImportarObrasJob::class, ConciliarPessoasJob::class, ConcluirSincronizacaoJob::class]);
    }

    public function test_com_outra_em_andamento_avisa_e_sai_com_sucesso(): void
    {
        Bus::fake();
        $iniciada = now()->subMinutes(10);
        Sincronizacao::factory()->emAndamento()->create(['iniciada_em' => $iniciada]);

        $this->artisan('uau:sincronizar')
            ->expectsOutputToContain('Já há uma sincronização em andamento, iniciada às '.$iniciada->format('H:i'))
            ->assertSuccessful();

        $this->assertSame(1, Sincronizacao::count());
        Bus::assertNothingDispatched();
    }

    public function test_agendado_todo_dia_no_horario_configurado(): void
    {
        $this->assertSame('0 5 * * *', $this->evento()->expression);
        $this->assertTrue($this->evento()->onOneServer);
        $this->assertTrue($this->evento()->withoutOverlapping);

        config(['uau.sincronizacao.horario' => '06:30']);
        $this->assertSame('30 6 * * *', $this->evento()->expression);
    }

    public function test_aparece_no_schedule_list(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('uau:sincronizar')
            ->assertSuccessful();
    }

    private function evento()
    {
        // Recria o agendamento com a config atual, como o schedule:run faz a cada minuto.
        $this->app->forgetInstance(Schedule::class);
        $schedule = $this->app->make(Schedule::class);
        (fn () => $this->schedule($schedule))->call($this->app->make(Kernel::class));

        return collect($schedule->events())->first(fn ($evento) => str_contains($evento->command, 'uau:sincronizar'));
    }
}

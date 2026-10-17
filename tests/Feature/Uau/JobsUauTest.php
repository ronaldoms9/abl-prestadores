<?php

namespace Tests\Feature\Uau;

use App\Actions\Uau\ImportacaoSuspensa;
use App\Actions\Uau\ImportarEmpresas;
use App\Actions\Uau\ImportarObras;
use App\Actions\Uau\ImportarPessoa;
use App\Integrations\Uau\Conciliacao;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Integrations\Uau\Jobs\ImportarPessoaJob;
use App\Integrations\Uau\OperacaoUau;
use App\Models\Empresa;
use App\Models\IntegracaoLog;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class JobsUauTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    public function test_uau_indisponivel_sobe_para_o_worker_tentar_de_novo(): void
    {
        $this->simularUau()->falharEm(OperacaoUau::EmpresasAtivas, new UauIndisponivel('UAU fora do ar.', 503));
        $job = (new ImportarEmpresasJob())->withFakeQueueInteractions();

        try {
            app()->call([$job, 'handle']);
            $this->fail('Deveria ter relançado UauIndisponivel.');
        } catch (UauIndisponivel $e) {
            $this->assertSame('UAU fora do ar.', $e->getMessage());
        }

        // Quem agenda a nova tentativa é o worker, com o $backoff do job.
        $job->assertNotReleased();
        $job->assertNotFailed();
        $this->assertSame([60, 300, 900, 3600], $job->backoff);
        $this->assertSame(4, $job->tries);
    }

    public function test_uau_rejeitou_falha_sem_retentar(): void
    {
        $this->simularUau()->falharEm(OperacaoUau::EmpresasAtivas, new UauRejeitou('Resposta fora do padrão.', 400));
        $job = (new ImportarEmpresasJob())->withFakeQueueInteractions();

        app()->call([$job, 'handle']);

        $job->assertFailedWith(UauRejeitou::class);
        $job->assertNotReleased();
    }

    public function test_importacao_suspensa_falha_sem_retentar(): void
    {
        $this->simularUau(['empresas-ativas.json' => []]);
        Empresa::factory()->create(['ativo' => true]);
        $job = (new ImportarEmpresasJob())->withFakeQueueInteractions();

        app()->call([$job, 'handle']);

        $job->assertFailedWith(ImportacaoSuspensa::class);
        $job->assertNotReleased();
    }

    public function test_jobs_vao_para_a_fila_uau_e_garantem_unicidade(): void
    {
        Queue::fake();

        ImportarEmpresasJob::dispatch();
        ImportarEmpresasJob::dispatch();

        ImportarObrasJob::dispatch();
        ImportarObrasJob::dispatch();

        ImportarPessoaJob::dispatch('529.982.247-25');
        ImportarPessoaJob::dispatch('52998224725');

        ConciliarPessoasJob::dispatch();
        ConciliarPessoasJob::dispatch();

        Queue::assertPushedOn('uau', ImportarEmpresasJob::class);
        Queue::assertPushedTimes(ImportarEmpresasJob::class, 1);

        Queue::assertPushedOn('uau', ImportarObrasJob::class);
        Queue::assertPushedTimes(ImportarObrasJob::class, 1);

        Queue::assertPushedOn('uau', ImportarPessoaJob::class);
        Queue::assertPushedTimes(ImportarPessoaJob::class, 1);

        Queue::assertPushedOn('uau', ConciliarPessoasJob::class);
        Queue::assertPushedTimes(ConciliarPessoasJob::class, 1);
    }

    public function test_conciliar_pessoas_despacha_um_job_por_pessoa_local_nao_excluida(): void
    {
        $pf1 = PessoaFisica::factory()->create(['cpf' => '52998224725']);
        $pf2 = PessoaFisica::factory()->create(['cpf' => '74620153826']);
        $pfExcluida = PessoaFisica::factory()->create(['cpf' => '03174682940']);
        $pfExcluida->delete();

        $pj1 = PessoaJuridica::factory()->create(['cnpj' => '27543198000106']);
        $pjExcluida = PessoaJuridica::factory()->create(['cnpj' => '04252011000110']);
        $pjExcluida->delete();

        Queue::fake();

        (new ConciliarPessoasJob())->handle();

        Queue::assertPushed(ImportarPessoaJob::class, 3);
        Queue::assertPushed(ImportarPessoaJob::class, fn ($job) => $job->documento === '52998224725');
        Queue::assertPushed(ImportarPessoaJob::class, fn ($job) => $job->documento === '74620153826');
        Queue::assertPushed(ImportarPessoaJob::class, fn ($job) => $job->documento === '27543198000106');
        Queue::assertNotPushed(ImportarPessoaJob::class, fn ($job) => $job->documento === '03174682940');
        Queue::assertNotPushed(ImportarPessoaJob::class, fn ($job) => $job->documento === '04252011000110');
    }

    public function test_cadeia_executa_em_ordem_e_sincroniza_tudo(): void
    {
        $this->simularUau();
        $pf = PessoaFisica::factory()->create(['cpf' => '52998224725', 'sincronizado_em' => null]);
        $pj = PessoaJuridica::factory()->create(['cnpj' => '27543198000106', 'sincronizado_em' => null]);

        Conciliacao::cadeia()->dispatch();

        $this->assertSame(3, Empresa::count());
        $this->assertSame(3, Obra::count());
        $this->assertNotNull($pf->fresh()->sincronizado_em);
        $this->assertNotNull($pj->fresh()->sincronizado_em);
    }

    public function test_primeiro_elo_da_cadeia_falhando_impede_os_seguintes(): void
    {
        $this->simularUau()->falharEm(OperacaoUau::EmpresasAtivas, new UauIndisponivel('UAU fora do ar.', 503));
        PessoaFisica::factory()->create(['cpf' => '52998224725', 'sincronizado_em' => null]);

        try {
            Conciliacao::cadeia()->dispatch();
            $this->fail('Deveria ter falhado com UauIndisponivel.');
        } catch (UauIndisponivel) {
        }

        $this->assertSame(0, Empresa::count());
        $this->assertSame(0, Obra::count());
        $this->assertNull(PessoaFisica::sole()->sincronizado_em);
    }

    public function test_tentativa_do_job_aparece_em_integracao_logs(): void
    {
        $this->simularUau();
        $job = (new ImportarEmpresasJob())->withFakeQueueInteractions();
        $job->job->attempts = 3;

        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('integracao_logs', [
            'operacao' => 'empresas-ativas',
            'tentativas' => 3,
        ]);
    }
}

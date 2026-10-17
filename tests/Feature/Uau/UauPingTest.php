<?php

namespace Tests\Feature\Uau;

use App\Integrations\Uau\UauGateway;
use App\Models\IntegracaoLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UauPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contra_o_simulado_sai_com_zero_e_mostra_a_versao(): void
    {
        $this->artisan('uau:ping')
            ->expectsOutputToContain('Modo: simulado')
            ->expectsOutputToContain('Conectado. Versão do UAU: UAU API 1.0 (simulada)')
            ->assertExitCode(0);
    }

    public function test_documento_encontrado_mostra_so_tipo_codigo_e_situacao(): void
    {
        $this->artisan('uau:ping', ['--documento' => '746.201.538-26'])
            ->expectsOutput('Pessoa encontrada: PF, código 1002, inativa.')
            ->doesntExpectOutputToContain('Carlos')
            ->assertExitCode(0);
    }

    public function test_documento_desconhecido_mostra_nao_encontrada(): void
    {
        $this->artisan('uau:ping', ['--documento' => '11144477735'])
            ->expectsOutput('Pessoa não encontrada.')
            ->assertExitCode(0);
    }

    public function test_configuracao_invalida_sai_com_um_e_explica_sem_tocar_na_rede(): void
    {
        Http::preventStrayRequests();
        config(['uau' => array_merge(config('uau'), [
            'fake' => false,
            'base_url' => 'https://uau.test/uauAPI/api/v1.0',
            'login' => 'login-longo-demais',
            'senha' => 'x',
            'token_integracao' => 'y',
        ])]);
        $this->app->forgetInstance(UauGateway::class);

        $this->artisan('uau:ping')
            ->expectsOutputToContain('Modo: real. URL: https://uau.test/uauAPI/api/v1.0')
            ->expectsOutputToContain('UAU_LOGIN tem 18 caracteres')
            ->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertSame(0, IntegracaoLog::count());
    }

    public function test_falha_do_uau_sai_com_um_e_aponta_o_log(): void
    {
        config(['uau' => array_merge(config('uau'), [
            'fake' => false,
            'base_url' => 'https://uau.test/uauAPI/api/v1.0',
            'login' => 'ablapi',
            'senha' => 'x',
            'token_integracao' => 'y',
        ])]);
        $this->app->forgetInstance(UauGateway::class);
        Http::fake(['https://uau.test/*' => Http::response(['Mensagem' => 'Login do usuário ou a senha estão incorretos.'], 400)]);

        $this->artisan('uau:ping')
            ->expectsOutputToContain('Falhou: Login do usuário ou a senha estão incorretos.')
            ->expectsOutputToContain('Detalhes em integracao_logs, id '.(IntegracaoLog::max('id') + 1))
            ->assertExitCode(1);
    }
}

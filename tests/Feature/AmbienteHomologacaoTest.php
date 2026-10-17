<?php

namespace Tests\Feature;

use App\Models\Obra;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Ambiente de homologação (D9.1 da fatia 9). */
class AmbienteHomologacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_faixa_aparece_so_na_homologacao(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('faixa-homologacao');

        $this->app['env'] = 'homologacao';

        $this->get(route('login'))->assertSee('Ambiente de homologação.');
        $this->actingAs($this->usuarioCom('user-list'))->get(route('home'))->assertSee('faixa-homologacao', false);
    }

    public function test_carregamento_sob_demanda_quebra_nos_testes(): void
    {
        $this->expectException(LazyLoadingViolationException::class);

        $this->obrasLidasDoBanco()->first()->empresa;
    }

    public function test_carregamento_sob_demanda_so_vai_para_o_log_na_homologacao(): void
    {
        $this->app['env'] = 'homologacao';
        Log::spy();

        $empresa = $this->obrasLidasDoBanco()->first()->empresa;

        $this->assertNotNull($empresa);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($mensagem) => $mensagem === 'Carregamento sob demanda de relação: '.Obra::class.'::empresa');
    }

    public function test_horizon_tem_supervisores_na_homologacao(): void
    {
        $this->assertSame(
            array_keys(config('horizon.environments.production')),
            array_keys(config('horizon.environments.homologacao')),
        );
    }

    /** Duas obras lidas do banco: o Laravel só barra o carregamento com mais de um model na consulta. */
    private function obrasLidasDoBanco()
    {
        Obra::factory()->count(2)->create();

        return Obra::all();
    }
}

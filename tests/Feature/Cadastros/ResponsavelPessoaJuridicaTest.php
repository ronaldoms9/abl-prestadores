<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Cadastros\DefinirResponsavel;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\ResponsavelPessoaJuridica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsavelPessoaJuridicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_define_o_responsavel_e_grava_localmente(): void
    {
        $pj = PessoaJuridica::factory()->create();
        $pf = PessoaFisica::factory()->create();

        $vinculo = app(DefinirResponsavel::class)->executar($pj, $pf, now()->parse('2026-11-17'));

        $this->assertDatabaseHas('pessoa_juridica_responsaveis', [
            'id' => $vinculo->id,
            'pessoa_juridica_id' => $pj->id,
            'pessoa_fisica_id' => $pf->id,
            'fim' => null,
        ]);
        $this->assertTrue($pj->fresh()->responsavelAtual->pessoaFisica->is($pf));
        $this->assertSame('2026-11-17', $vinculo->inicio->toDateString());
    }

    public function test_indicar_o_mesmo_responsavel_nao_duplica(): void
    {
        $pj = PessoaJuridica::factory()->create();
        $pf = PessoaFisica::factory()->create();

        $acao = app(DefinirResponsavel::class);
        $primeiro = $acao->executar($pj, $pf);
        $segundo = $acao->executar($pj, $pf);

        $this->assertTrue($primeiro->is($segundo));
        $this->assertSame(1, ResponsavelPessoaJuridica::count());
    }

    public function test_trocar_o_responsavel_encerra_o_anterior_e_guarda_o_historico(): void
    {
        $pj = PessoaJuridica::factory()->create();
        $antigo = PessoaFisica::factory()->create();
        $novo = PessoaFisica::factory()->create();

        $acao = app(DefinirResponsavel::class);
        $acao->executar($pj, $antigo, now()->parse('2026-01-10'));
        $acao->executar($pj, $novo, now()->parse('2026-11-17'));

        $pj = $pj->fresh();
        $this->assertTrue($pj->responsavelAtual->pessoaFisica->is($novo));
        $this->assertSame(2, $pj->responsaveis()->count());
        $this->assertSame('2026-11-17', ResponsavelPessoaJuridica::where('pessoa_fisica_id', $antigo->id)->first()->fim->toDateString());
        $this->assertSame(1, ResponsavelPessoaJuridica::vigentes()->count());
    }

    public function test_a_mesma_pessoa_fisica_pode_ser_responsavel_de_varias_pjs(): void
    {
        $pf = PessoaFisica::factory()->create();
        $acao = app(DefinirResponsavel::class);

        $acao->executar(PessoaJuridica::factory()->create(), $pf);
        $acao->executar(PessoaJuridica::factory()->create(), $pf);

        $this->assertSame(2, $pf->responsabilidades()->vigentes()->count());
    }

    public function test_detalhe_da_pj_mostra_o_responsavel(): void
    {
        $pj = PessoaJuridica::factory()->create();
        $pf = PessoaFisica::factory()->create(['nome' => 'Maria da Silva']);
        app(DefinirResponsavel::class)->executar($pj, $pf);

        $this->actingAs($this->usuarioCom('pessoa-list'))
            ->get(route('pessoa-juridica.show', $pj->id))
            ->assertOk()
            ->assertSee('Responsável')
            ->assertSee('Maria da Silva');
    }
}

<?php

namespace Tests\Feature\Cadastros;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\ResponsavelPessoaJuridica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CadastroPessoaJuridicaTest extends TestCase
{
    use RefreshDatabase;

    private function dados(PessoaFisica $responsavel, array $extra = []): array
    {
        return array_replace_recursive([
            'cnpj' => '11.222.333/0001-81',
            'razao_social' => 'MAR Engenharia Ltda',
            'nome_fantasia' => 'MAR',
            'regime_tributario' => 'Simples Nacional',
            'email' => 'contato@mar.example',
            'responsavel_id' => $responsavel->id,
            'endereco' => ['cidade' => 'Goiânia', 'uf' => 'GO'],
        ], $extra);
    }

    private function enviar(array $dados, ?int $id = null)
    {
        return $this->actingAs($this->usuarioCom('pessoa-create', 'pessoa-edit'))
            ->postJson(route('pessoa-juridica.store', $id ? [$id] : []), $dados);
    }

    public function test_cria_a_pj_e_o_vinculo_com_o_responsavel(): void
    {
        $pf = PessoaFisica::factory()->create();

        $this->enviar($this->dados($pf))->assertOk()->assertJson(['success' => true]);

        $pj = PessoaJuridica::with('responsavelAtual')->firstOrFail();
        $this->assertSame('11222333000181', $pj->cnpj);
        $this->assertSame($pf->id, $pj->responsavelAtual->pessoa_fisica_id);
        $this->assertNull($pj->uau_codigo);
    }

    public function test_responsavel_e_obrigatorio_e_precisa_existir(): void
    {
        $pf = PessoaFisica::factory()->create();

        $semResponsavel = $this->dados($pf);
        unset($semResponsavel['responsavel_id']);
        $this->enviar($semResponsavel)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'responsável'));

        $this->enviar($this->dados($pf, ['responsavel_id' => 999999]))->assertStatus(422);
        $this->assertSame(0, PessoaJuridica::count());
    }

    public function test_cnpj_invalido_e_repetido_sao_recusados(): void
    {
        $pf = PessoaFisica::factory()->create();

        $this->enviar($this->dados($pf, ['cnpj' => '11.111.111/1111-11']))->assertStatus(422);
        $this->enviar($this->dados($pf))->assertOk();
        $this->enviar($this->dados($pf, ['razao_social' => 'Outra']))->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'CNPJ'));
    }

    public function test_trocar_o_responsavel_encerra_o_anterior_e_audita(): void
    {
        $antigo = PessoaFisica::factory()->create(['nome' => 'Antigo']);
        $novo = PessoaFisica::factory()->create(['nome' => 'Novo']);
        $this->enviar($this->dados($antigo))->assertOk();
        $pj = PessoaJuridica::firstOrFail();

        $this->enviar($this->dados($novo), $pj->id)->assertOk();

        $this->assertSame($novo->id, $pj->fresh()->responsavelAtual->pessoa_fisica_id);
        $this->assertSame(2, ResponsavelPessoaJuridica::where('pessoa_juridica_id', $pj->id)->count());
        $this->assertNotNull(ResponsavelPessoaJuridica::where('pessoa_fisica_id', $antigo->id)->first()->fim);
        $registro = Activity::where('event', 'pessoa-juridica-responsavel-alterado')->latest('id')->firstOrFail();
        $this->assertSame('Antigo', $registro->properties['antes']);
        $this->assertSame('Novo', $registro->properties['depois']);
    }

    public function test_salvar_de_novo_com_o_mesmo_responsavel_nao_cria_vinculo(): void
    {
        $pf = PessoaFisica::factory()->create();
        $this->enviar($this->dados($pf))->assertOk();
        $pj = PessoaJuridica::firstOrFail();

        $this->enviar($this->dados($pf, ['nome_fantasia' => 'MAR 2']), $pj->id)->assertOk();

        $this->assertSame(1, ResponsavelPessoaJuridica::count());
        $this->assertSame('MAR 2', $pj->fresh()->nome_fantasia);
    }

    public function test_desativar_e_regime_invalido(): void
    {
        $pf = PessoaFisica::factory()->create();
        $this->enviar($this->dados($pf, ['regime_tributario' => 'Inventado']))->assertStatus(422);

        $this->enviar($this->dados($pf))->assertOk();
        $pj = PessoaJuridica::firstOrFail();
        $this->enviar($this->dados($pf, ['ativo' => 0]), $pj->id)->assertOk();
        $this->assertFalse($pj->fresh()->ativo);
    }

    public function test_formulario_de_edicao_traz_o_responsavel_e_o_aviso_de_uau(): void
    {
        $pf = PessoaFisica::factory()->create(['nome' => 'Responsável Teste']);
        $this->enviar($this->dados($pf))->assertOk();
        $pj = PessoaJuridica::firstOrFail();
        $pj->update(['uau_codigo' => 123]);

        $this->actingAs($this->usuarioCom('pessoa-edit'))
            ->get(route('pessoa-juridica.form', $pj->id))
            ->assertOk()
            ->assertSee('Responsável Teste')
            ->assertSee('vem do UAU');
    }
}

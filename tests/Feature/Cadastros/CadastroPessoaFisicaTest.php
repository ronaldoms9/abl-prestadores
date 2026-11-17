<?php

namespace Tests\Feature\Cadastros;

use App\Models\PessoaFisica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CadastroPessoaFisicaTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_replace_recursive([
            'nome' => 'Maria da Silva',
            'cpf' => '529.982.247-25',
            'nascimento' => '1990-03-15',
            'email' => 'maria@example.com',
            'telefone' => '(62) 99999-1234',
            'endereco' => ['cep' => '74805-000', 'logradouro' => 'Rua 1', 'numero' => '10', 'cidade' => 'Goiânia', 'uf' => 'GO'],
        ], $extra);
    }

    private function criar(array $extra = [])
    {
        return $this->actingAs($this->usuarioCom('pessoa-create', 'pessoa-edit'))
            ->postJson(route('pessoa-fisica.store'), $this->dados($extra));
    }

    public function test_cria_a_pessoa_com_cpf_so_digitos_e_endereco(): void
    {
        $this->criar()->assertOk()->assertJson(['success' => true]);

        $pf = PessoaFisica::firstOrFail();
        $this->assertSame('52998224725', $pf->cpf);
        $this->assertSame('Maria da Silva', $pf->nome);
        $this->assertSame('Goiânia', $pf->endereco->cidade);
        $this->assertSame('74805000', $pf->endereco->cep);
        $this->assertNull($pf->uau_codigo);
        $this->assertTrue($pf->ativo);
    }

    public function test_recusa_cpf_invalido_e_cpf_repetido_com_mensagem(): void
    {
        $this->criar(['cpf' => '111.111.111-11'])->assertStatus(422)->assertJson(['success' => false]);
        $this->assertSame(0, PessoaFisica::count());

        $this->criar()->assertOk();
        $this->criar(['nome' => 'Outra'])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'CPF'));
    }

    public function test_edita_sem_conflitar_com_o_proprio_cpf_e_audita_so_o_que_mudou(): void
    {
        $this->criar();
        $pf = PessoaFisica::firstOrFail();

        $this->actingAs($this->usuarioCom('pessoa-edit'))
            ->postJson(route('pessoa-fisica.store', $pf->id), $this->dados(['nome' => 'Maria Souza']))
            ->assertOk();

        $this->assertSame('Maria Souza', $pf->fresh()->nome);
        $registro = Activity::where('event', 'pessoa-fisica-alterado')->latest('id')->firstOrFail();
        $this->assertSame(['nome'], array_keys($registro->properties['attributes']));
    }

    public function test_cria_e_edita_exigem_permissoes_diferentes(): void
    {
        $pf = PessoaFisica::factory()->create();

        $this->actingAs($this->usuarioCom('pessoa-create'))
            ->postJson(route('pessoa-fisica.store', $pf->id), $this->dados())->assertForbidden();
        $this->actingAs($this->usuarioCom('pessoa-edit'))
            ->postJson(route('pessoa-fisica.store'), $this->dados())->assertForbidden();
    }

    public function test_dados_bancarios_uma_principal_e_remocao(): void
    {
        $this->criar(['bancos' => [
            ['banco' => '001 - BB', 'agencia' => '1', 'conta' => '2', 'tipo' => 'corrente', 'tipo_chave_pix' => 'cpf', 'chave_pix' => '529.982.247-25'],
            ['banco' => '104 - CEF', 'agencia' => '3', 'conta' => '4', 'tipo' => 'poupanca'],
        ], 'principal' => 1])->assertOk();

        $pf = PessoaFisica::firstOrFail();
        $this->assertSame(2, $pf->dadosBancarios()->count());
        $this->assertSame('104 - CEF', $pf->dadosBancarios()->where('principal', true)->value('banco'));

        $primeira = $pf->dadosBancarios()->where('banco', '001 - BB')->first();
        $this->actingAs($this->usuarioCom('pessoa-edit'))
            ->postJson(route('pessoa-fisica.store', $pf->id), $this->dados(['bancos' => [
                ['id' => $primeira->id, 'banco' => '001 - BB', 'agencia' => '1', 'conta' => '2', 'tipo' => 'corrente'],
            ]]))->assertOk();

        $this->assertSame(1, $pf->dadosBancarios()->count());
        $this->assertTrue((bool) $pf->dadosBancarios()->first()->principal);
        $this->assertNull($pf->dadosBancarios()->first()->chave_pix);
        $this->assertTrue(Activity::where('event', 'pessoa-dados-bancarios-alterados')->exists());
    }

    public function test_chave_pix_e_validada_pelo_tipo(): void
    {
        $this->criar(['bancos' => [
            ['banco' => 'X', 'agencia' => '1', 'conta' => '2', 'tipo' => 'corrente', 'tipo_chave_pix' => 'email', 'chave_pix' => 'nao-e-email'],
        ]])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'chave PIX'));

        $this->criar(['bancos' => [
            ['banco' => 'X', 'agencia' => '1', 'conta' => '2', 'tipo' => 'corrente', 'tipo_chave_pix' => 'telefone', 'chave_pix' => '(62) 99999-1234'],
        ]])->assertOk();
    }

    public function test_auditoria_de_dados_bancarios_nao_guarda_conta_nem_chave(): void
    {
        $this->criar(['bancos' => [['banco' => 'X', 'agencia' => '1234', 'conta' => '98765-0', 'tipo' => 'corrente', 'tipo_chave_pix' => 'cpf', 'chave_pix' => '52998224725']]]);

        $json = Activity::where('event', 'pessoa-dados-bancarios-alterados')->firstOrFail()->properties->toJson();
        $this->assertStringNotContainsString('98765', $json);
        $this->assertStringNotContainsString('52998224725', $json);
    }

    public function test_detalhe_mostra_bancos_e_historico(): void
    {
        $this->criar(['bancos' => [['banco' => '001 - BB', 'agencia' => '1', 'conta' => '2', 'tipo' => 'corrente']]]);
        $pf = PessoaFisica::firstOrFail();

        $this->actingAs($this->usuarioCom('pessoa-list'))
            ->get(route('pessoa-fisica.show', $pf->id))
            ->assertOk()
            ->assertSee('001 - BB')
            ->assertSee('Pessoa física criada');
    }

    public function test_busca_para_responsavel_por_nome_ou_cpf_so_ativos(): void
    {
        PessoaFisica::factory()->create(['nome' => 'Joana Prado', 'cpf' => '52998224725']);
        PessoaFisica::factory()->inativo()->create(['nome' => 'Joana Inativa']);

        $usuario = $this->usuarioCom('pessoa-edit');
        $porNome = $this->actingAs($usuario)->getJson(route('pessoa-fisica.buscar', ['q' => 'joana']))->assertOk()->json();
        $porCpf = $this->getJson(route('pessoa-fisica.buscar', ['q' => '529.982']))->json();

        $this->assertCount(1, $porNome);
        $this->assertStringContainsString('Joana Prado', $porNome[0]['texto']);
        $this->assertCount(1, $porCpf);
    }

    public function test_sincronizar_so_campos_da_sincronizacao_nao_gera_auditoria(): void
    {
        $pf = PessoaFisica::factory()->create();
        $antes = Activity::count();

        $pf->update(['sincronizado_em' => now(), 'uau_alterado_em' => now()]);

        $this->assertSame($antes, Activity::count());
    }
}

<?php

namespace Tests\Feature\Cadastros;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Models\VinculoSugerido;
use App\Support\Endereco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaFisicaTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioCom('pessoa-list');
    }

    public function test_busca_por_cpf_com_e_sem_mascara_e_pelo_comeco(): void
    {
        PessoaFisica::factory()->create(['cpf' => '52998224725', 'nome' => 'Mariana Alves']);
        PessoaFisica::factory()->create(['cpf' => '74620153826', 'nome' => 'Joaquim Souza']);

        $this->assertSame(['Mariana Alves'], $this->nomes(['busca' => '529.982.247-25']));
        $this->assertSame(['Mariana Alves'], $this->nomes(['busca' => '52998224725']));
        $this->assertSame(['Mariana Alves'], $this->nomes(['busca' => '529.982']));
        $this->assertSame(['Joaquim Souza'], $this->nomes(['busca' => 'souza']));
        $this->assertSame([], $this->nomes(['busca' => '111.444']));
    }

    public function test_porcentagem_digitada_nao_vira_curinga(): void
    {
        PessoaFisica::factory()->create(['nome' => 'Ana Lima']);
        PessoaFisica::factory()->create(['nome' => 'Ana 100% Lima']);

        $this->assertSame(['Ana 100% Lima'], $this->nomes(['busca' => '100%']));
        $this->assertSame([], $this->nomes(['busca' => 'Ana_Lima']));
    }

    public function test_situacao_ativos_por_padrao_e_excluida_nunca_aparece(): void
    {
        PessoaFisica::factory()->create(['nome' => 'Ativa']);
        PessoaFisica::factory()->inativo()->create(['nome' => 'Inativa']);
        PessoaFisica::factory()->create(['nome' => 'Excluída'])->delete();

        $this->assertSame(['Ativa'], $this->nomes([]));
        $this->assertSame(['Inativa'], $this->nomes(['situacao' => 'inativos']));
        $this->assertEqualsCanonicalizing(['Ativa', 'Inativa'], $this->nomes(['situacao' => 'todos']));
    }

    public function test_lista_formata_o_cpf_e_traz_so_as_colunas_da_tela(): void
    {
        PessoaFisica::factory()->create(['cpf' => '03174682940']);

        $linha = $this->linhas([])[0];

        $this->assertSame('031.746.829-40', $linha['cpf']);
        $this->assertStringContainsString('Ativo', $linha['situacao']);
        $this->assertSame(['cpf', 'nome', 'email', 'telefone', 'situacao', 'sincronizado_em', 'action'], array_keys($linha));
    }

    public function test_detalhe_mostra_endereco_e_pj_sugerida(): void
    {
        $pessoa = PessoaFisica::factory()->create([
            'nome' => 'Mariana Alves',
            'endereco' => new Endereco(logradouro: 'Rua 10', numero: '15', bairro: 'Setor Oeste', cidade: 'Goiânia', uf: 'GO', cep: '74120020'),
        ]);
        $pj = PessoaJuridica::factory()->create(['cnpj' => '27543198000106', 'razao_social' => 'MAR Engenharia Ltda']);
        VinculoSugerido::factory()->create(['pessoa_fisica_id' => $pessoa->id, 'pessoa_juridica_id' => $pj->id]);

        $this->actingAs($this->usuario)
            ->get(route('pessoa-fisica.show', $pessoa->id))
            ->assertOk()
            ->assertSee('Mariana Alves')
            ->assertSee('Rua 10, 15')
            ->assertSee('Goiânia/GO')
            ->assertSee('27.543.198/0001-06')
            ->assertSee('MAR Engenharia Ltda');
    }

    public function test_detalhe_sem_vinculo_explica_de_onde_vem(): void
    {
        $pessoa = PessoaFisica::factory()->create();

        $this->actingAs($this->usuario)
            ->get(route('pessoa-fisica.show', $pessoa->id))
            ->assertSee('Os vínculos são sugeridos pela carga da planilha');
    }

    public function test_tela_abre(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('pessoa-fisica.index'))
            ->assertOk()
            ->assertSee('Nome ou CPF, com ou sem máscara');
    }

    private function nomes(array $filtros): array
    {
        return array_column($this->linhas($filtros), 'nome');
    }

    private function linhas(array $filtros): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('pessoa-fisica.index', $filtros), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');
    }
}

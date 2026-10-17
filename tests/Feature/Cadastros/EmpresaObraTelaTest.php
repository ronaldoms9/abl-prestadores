<?php

namespace Tests\Feature\Cadastros;

use App\Models\Empresa;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaObraTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioCom('empresa-list', 'obra-list');
    }

    public function test_lista_de_empresas_conta_so_as_obras_ativas_e_busca_por_cnpj(): void
    {
        $empresa = Empresa::factory()->create(['cnpj' => '11222333000181', 'razao_social' => 'Alfa Construções']);
        Obra::factory()->count(2)->create(['empresa_id' => $empresa->id]);
        Obra::factory()->create(['empresa_id' => $empresa->id, 'ativo' => false]);
        Empresa::factory()->create(['cnpj' => '04252011000110', 'razao_social' => 'Beta Incorporações']);

        $linhas = $this->linhas('empresa.index', ['busca' => '11.222.333/0001-81']);

        $this->assertCount(1, $linhas);
        $this->assertSame('Alfa Construções', $linhas[0]['razao_social']);
        $this->assertSame('11.222.333/0001-81', $linhas[0]['cnpj']);
        $this->assertSame(2, $linhas[0]['obras_ativas_count']);
    }

    public function test_detalhe_da_empresa_lista_as_suas_obras(): void
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Alfa Construções']);
        Obra::factory()->create(['empresa_id' => $empresa->id, 'descricao' => 'Residencial Aurora']);
        Obra::factory()->create(['empresa_id' => $empresa->id, 'descricao' => 'Torre Inativa', 'ativo' => false]);
        Obra::factory()->create(['descricao' => 'Obra de outra empresa']);

        $this->actingAs($this->usuario)
            ->get(route('empresa.show', $empresa->id))
            ->assertOk()
            ->assertSee('Obras (2)')
            ->assertSee('Residencial Aurora')
            ->assertSee('Torre Inativa')
            ->assertDontSee('Obra de outra empresa');
    }

    public function test_obras_filtram_por_empresa_e_buscam_por_codigo(): void
    {
        $alfa = Empresa::factory()->create(['uau_codigo' => 1, 'razao_social' => 'Alfa']);
        $beta = Empresa::factory()->create(['uau_codigo' => 2, 'razao_social' => 'Beta']);
        Obra::factory()->create(['empresa_id' => $alfa->id, 'uau_codigo' => 'OB01', 'descricao' => 'Aurora']);
        Obra::factory()->create(['empresa_id' => $beta->id, 'uau_codigo' => 'OB01', 'descricao' => 'Boreal']);
        Obra::factory()->create(['empresa_id' => $beta->id, 'uau_codigo' => 'OB02', 'descricao' => 'Cristal']);

        $this->assertSame(['Boreal', 'Cristal'], $this->descricoes(['empresa' => $beta->id]));
        $this->assertSame(['Aurora', 'Boreal'], $this->descricoes(['busca' => 'ob01']));
        $this->assertSame(['Boreal'], $this->descricoes(['busca' => 'ob01', 'empresa' => $beta->id]));
        $this->assertSame('2 · Beta', $this->linhas('obra.index', ['busca' => 'cristal'])[0]['empresa']);
    }

    public function test_status_da_obra_mostra_o_codigo_sem_mapa_e_o_rotulo_com_mapa(): void
    {
        $obra = Obra::factory()->create(['status_uau' => 1]);

        $this->assertSame('Código 1', $this->linhas('obra.index', [])[0]['status_uau']);

        config(['uau.status_obra' => [1 => 'Em andamento']]);
        $this->assertSame('Em andamento', $this->linhas('obra.index', [])[0]['status_uau']);
        $this->actingAs($this->usuario)->get(route('obra.show', $obra->id))->assertSee('Em andamento');
    }

    public function test_obra_de_empresa_excluida_ainda_mostra_a_empresa(): void
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Empresa Antiga']);
        $obra = Obra::factory()->create(['empresa_id' => $empresa->id]);
        $empresa->delete();

        $this->assertStringContainsString('Empresa Antiga', $this->linhas('obra.index', [])[0]['empresa']);
        $this->actingAs($this->usuario)->get(route('obra.show', $obra->id))->assertOk()->assertSee('Empresa Antiga');
    }

    public function test_telas_abrem_com_o_filtro_de_empresa(): void
    {
        Empresa::factory()->create(['uau_codigo' => 7, 'razao_social' => 'Gama']);

        $this->actingAs($this->usuario)->get(route('empresa.index'))->assertOk();
        $this->actingAs($this->usuario)->get(route('obra.index'))->assertOk()->assertSee('7 · Gama');
    }

    private function descricoes(array $filtros): array
    {
        $descricoes = array_column($this->linhas('obra.index', $filtros), 'descricao');
        sort($descricoes);

        return $descricoes;
    }

    private function linhas(string $rota, array $filtros): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route($rota, $filtros), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');
    }
}

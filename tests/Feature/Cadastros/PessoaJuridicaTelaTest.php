<?php

namespace Tests\Feature\Cadastros;

use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Models\VinculoSugerido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaJuridicaTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioCom('pessoa-list');
    }

    public function test_busca_por_cnpj_com_mascara_razao_social_e_nome_fantasia(): void
    {
        PessoaJuridica::factory()->create(['cnpj' => '27543198000106', 'razao_social' => 'MAR Engenharia Ltda', 'nome_fantasia' => 'MAR']);
        PessoaJuridica::factory()->create(['cnpj' => '11222333000181', 'razao_social' => 'Alfa Serviços Ltda', 'nome_fantasia' => 'Construalfa']);

        $this->assertSame(['MAR Engenharia Ltda'], $this->razoes(['busca' => '27.543.198/0001-06']));
        $this->assertSame(['MAR Engenharia Ltda'], $this->razoes(['busca' => '27.543']));
        $this->assertSame(['Alfa Serviços Ltda'], $this->razoes(['busca' => 'serviços']));
        $this->assertSame(['Alfa Serviços Ltda'], $this->razoes(['busca' => 'construalfa']));
    }

    public function test_lista_formata_o_cnpj(): void
    {
        PessoaJuridica::factory()->create(['cnpj' => '04252011000110', 'nome_fantasia' => null]);

        $linha = $this->linhas([])[0];

        $this->assertSame('04.252.011/0001-10', $linha['cnpj']);
        $this->assertSame('—', $linha['nome_fantasia']);
    }

    public function test_detalhe_mostra_a_pf_sugerida(): void
    {
        $pj = PessoaJuridica::factory()->create(['razao_social' => 'MAR Engenharia Ltda']);
        $pf = PessoaFisica::factory()->create(['cpf' => '52998224725', 'nome' => 'Mariana Alves']);
        VinculoSugerido::factory()->create(['pessoa_fisica_id' => $pf->id, 'pessoa_juridica_id' => $pj->id]);
        $descartada = PessoaFisica::factory()->create(['nome' => 'Vínculo descartado']);
        VinculoSugerido::factory()->create(['pessoa_fisica_id' => $descartada->id, 'pessoa_juridica_id' => $pj->id])->delete();

        $this->actingAs($this->usuario)
            ->get(route('pessoa-juridica.show', $pj->id))
            ->assertOk()
            ->assertSee('MAR Engenharia Ltda')
            ->assertSee('529.982.247-25')
            ->assertSee('Mariana Alves')
            ->assertDontSee('Vínculo descartado');
    }

    private function razoes(array $filtros): array
    {
        return array_column($this->linhas($filtros), 'razao_social');
    }

    private function linhas(array $filtros): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('pessoa-juridica.index', $filtros), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');
    }
}

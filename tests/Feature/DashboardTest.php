<?php

namespace Tests\Feature;

use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\Remuneracao;
use App\Models\User;
use App\Enums\StatusColaborador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mostra_so_os_blocos_das_permissoes_do_usuario(): void
    {
        $colab = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        Remuneracao::factory()->create(['colaborador_id' => $colab->id, 'valor_mensal' => '5000.00', 'vigencia_inicio' => now()->subMonth(), 'vigencia_fim' => null]);

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('home'))
            ->assertOk()->assertSee('Colaboradores ativos')->assertDontSee('Remuneração mensal vigente')->assertDontSee('Documentos que pedem atenção');

        $this->actingAs($this->usuarioCom('colaborador-list', 'remuneracao-list'))->get(route('home'))
            ->assertOk()->assertSee('Remuneração mensal vigente')->assertSee('5.000,00');
    }

    public function test_lista_documento_vencido_com_o_nome_do_dono(): void
    {
        $colab = Colaborador::factory()->create(['status' => StatusColaborador::Ativo]);
        $tipo = DocumentoTipo::factory()->create(['exige_validade' => true]);
        Documento::factory()->create([
            'documentavel_type' => 'colaborador', 'documentavel_id' => $colab->id,
            'documento_tipo_id' => $tipo->id, 'tipo' => 'ASO', 'validade' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($this->usuarioCom('documento-list'))->get(route('home'))
            ->assertOk()->assertSee('Documentos que pedem atenção')->assertSee($colab->pessoaFisica->nome)->assertSee('Vencido');
    }

    public function test_sem_permissao_nenhuma_nao_quebra(): void
    {
        $this->actingAs($this->usuarioCom())->get(route('home'))->assertOk();
    }
}

<?php

namespace Tests\Feature\Models;

use App\Enums\StatusContrato;
use App\Enums\TipoCalculoBeneficio;
use App\Enums\TipoContrato;
use App\Models\AdmissaoChecklist;
use App\Models\AdmissaoChecklistItem;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Cargo;
use App\Models\CargoBeneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosBeneficiosE3Test extends TestCase
{
    use RefreshDatabase;

    public function test_cada_factory_cria_um_registro_valido_com_autoria(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        foreach ([
            ContratoModelo::class, Contrato::class, Beneficio::class, BeneficioValor::class,
            ColaboradorBeneficio::class, CargoBeneficio::class, AdmissaoChecklistItem::class, AdmissaoChecklist::class,
        ] as $classe) {
            $registro = $classe::factory()->create();
            $this->assertDatabaseHas($registro->getTable(), ['id' => $registro->id, 'created_by' => $usuario->id]);
        }
    }

    public function test_contrato_nasce_rascunho_e_aditivo_aponta_para_o_pai(): void
    {
        $colaborador = Colaborador::factory()->create();
        $modelo = ContratoModelo::factory()->create();
        $pai = Contrato::factory()->gerado()->create(['colaborador_id' => $colaborador->id, 'contrato_modelo_id' => $modelo->id]);
        $aditivo = Contrato::factory()->create([
            'colaborador_id' => $colaborador->id,
            'contrato_modelo_id' => $modelo->id,
            'contrato_pai_id' => $pai->id,
            'tipo' => TipoContrato::Aditivo,
        ]);

        $aditivo = Contrato::with(['pai', 'modelo', 'colaborador'])->find($aditivo->id);
        $pai = Contrato::with('filhos')->find($pai->id);

        $this->assertSame(StatusContrato::Rascunho, $aditivo->status);
        $this->assertSame(TipoContrato::Aditivo, $aditivo->tipo);
        $this->assertTrue($aditivo->pai->is($pai));
        $this->assertCount(1, $pai->filhos);
        $this->assertTrue($pai->status->imutavel());
        $this->assertFalse($aditivo->status->imutavel());
    }

    public function test_numero_do_contrato_e_unico(): void
    {
        Contrato::factory()->gerado('2026/0001')->create();

        $this->expectException(UniqueConstraintViolationException::class);
        Contrato::factory()->gerado('2026/0001')->create();
    }

    public function test_modelo_guarda_a_versao_anterior(): void
    {
        $v1 = ContratoModelo::factory()->create(['ativo' => false]);
        $v2 = ContratoModelo::factory()->create(['versao' => 2, 'modelo_anterior_id' => $v1->id]);

        $this->assertTrue(ContratoModelo::with('anterior')->find($v2->id)->anterior->is($v1));
        $this->assertSame([$v2->id], ContratoModelo::ativos()->pluck('id')->all());
    }

    public function test_valor_de_beneficio_tem_vigencia_com_fim_exclusivo(): void
    {
        $beneficio = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 400, 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-05-10']);
        $novo = BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 450, 'vigencia_inicio' => '2026-05-10']);

        $this->assertSame('400.00', BeneficioValor::vigentesEm('2026-05-09')->first()->valor);
        $this->assertTrue(BeneficioValor::vigentesEm('2026-05-10')->first()->is($novo));
        $this->assertSame([$novo->id], BeneficioValor::vigentes()->pluck('id')->all());
    }

    public function test_beneficio_tem_tipo_de_calculo(): void
    {
        $this->assertSame(TipoCalculoBeneficio::FixoMensal, Beneficio::factory()->create()->tipo_calculo);
        $this->assertSame(TipoCalculoBeneficio::PorDiaTrabalhado, Beneficio::factory()->porDia()->create()->tipo_calculo);
    }

    public function test_beneficio_padrao_do_cargo_e_unico(): void
    {
        $cargo = Cargo::factory()->create();
        $beneficio = Beneficio::factory()->create();
        CargoBeneficio::factory()->create(['cargo_id' => $cargo->id, 'beneficio_id' => $beneficio->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        CargoBeneficio::factory()->create(['cargo_id' => $cargo->id, 'beneficio_id' => $beneficio->id]);
    }

    public function test_checklist_guarda_copia_do_item_mesmo_se_a_configuracao_for_apagada(): void
    {
        $item = AdmissaoChecklistItem::factory()->create(['nome' => 'Ficha cadastral']);
        $colaborador = Colaborador::factory()->create();
        $linha = AdmissaoChecklist::factory()->create([
            'colaborador_id' => $colaborador->id,
            'admissao_checklist_item_id' => $item->id,
            'item' => $item->nome,
        ]);

        $item->forceDelete();

        $this->assertSame('Ficha cadastral', $linha->fresh()->item);
        $this->assertNull($linha->fresh()->admissao_checklist_item_id);
    }

    public function test_item_com_chave_e_automatico(): void
    {
        $this->assertTrue(AdmissaoChecklistItem::factory()->create(['chave' => 'pj_no_uau'])->automatico());
        $this->assertFalse(AdmissaoChecklistItem::factory()->create()->automatico());
    }

    public function test_colaborador_enxerga_contratos_beneficios_e_checklist(): void
    {
        $colaborador = Colaborador::factory()->create();
        Contrato::factory()->create(['colaborador_id' => $colaborador->id]);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $colaborador->id]);
        AdmissaoChecklist::factory()->create(['colaborador_id' => $colaborador->id]);

        $colaborador = Colaborador::withCount(['contratos', 'beneficios', 'checklistAdmissao'])->find($colaborador->id);

        $this->assertSame([1, 1, 1], [$colaborador->contratos_count, $colaborador->beneficios_count, $colaborador->checklist_admissao_count]);
    }
}

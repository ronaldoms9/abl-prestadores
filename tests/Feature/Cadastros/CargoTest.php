<?php

namespace Tests\Feature\Cadastros;

use App\Models\Cargo;
use App\Models\Colaborador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CargoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_do_cargo_com_faixa_e_auditoria(): void
    {
        $rh = $this->usuarioCom('cargo-list', 'cargo-create', 'cargo-edit');

        $this->actingAs($rh)->postJson(route('cargo.store'), ['nome' => 'Pedreiro', 'faixa_min' => '2500', 'faixa_max' => '4000.50'])->assertOk();
        $cargo = Cargo::firstOrFail();
        $this->assertSame('2500.00', $cargo->faixa_min);
        $this->assertSame('4000.50', $cargo->faixa_max);
        $this->assertTrue(Activity::where('event', 'cargo-criado')->exists());

        $this->postJson(route('cargo.store', $cargo->id), ['nome' => 'Pedreiro', 'faixa_min' => '', 'faixa_max' => '', 'ativo' => 0])->assertOk();
        $this->assertNull($cargo->fresh()->faixa_min);
        $this->assertFalse($cargo->fresh()->ativo);
    }

    public function test_faixa_maxima_nao_pode_ser_menor_que_a_minima_e_nome_e_unico(): void
    {
        $rh = $this->usuarioCom('cargo-create');
        $this->actingAs($rh)->postJson(route('cargo.store'), ['nome' => 'A', 'faixa_min' => '5000', 'faixa_max' => '3000'])->assertStatus(422);
        $this->postJson(route('cargo.store'), ['nome' => 'B'])->assertOk();
        $this->postJson(route('cargo.store'), ['nome' => 'B'])->assertStatus(422);
    }

    public function test_nao_exclui_cargo_com_colaboradores(): void
    {
        $cargo = Cargo::factory()->create();
        Colaborador::factory()->create(['cargo_id' => $cargo->id]);

        $this->actingAs($this->usuarioCom('cargo-edit'))->deleteJson(route('cargo.delete', $cargo->id))->assertStatus(400);
        $this->assertNotSoftDeleted($cargo);

        $livre = Cargo::factory()->create();
        $this->deleteJson(route('cargo.delete', $livre->id))->assertStatus(201);
        $this->assertSoftDeleted($livre);
    }

    public function test_lista_mostra_faixa_e_quantidade_de_colaboradores(): void
    {
        $cargo = Cargo::factory()->create(['nome' => 'Mestre de obras', 'faixa_min' => 5000, 'faixa_max' => 8000]);
        Colaborador::factory()->count(2)->create(['cargo_id' => $cargo->id]);

        $linha = $this->actingAs($this->usuarioCom('cargo-list'))
            ->getJson(route('cargo.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');

        $this->assertSame('Mestre de obras', $linha['nome']);
        $this->assertSame('R$ 5.000,00 a R$ 8.000,00', $linha['faixa']);
        $this->assertSame(2, $linha['colaboradores']);
    }

    public function test_colaborador_passa_a_poder_escolher_o_cargo_cadastrado(): void
    {
        Cargo::factory()->create(['nome' => 'Eletricista']);

        $this->actingAs($this->usuarioCom('colaborador-create'))
            ->get(route('colaborador.form'))->assertOk()->assertSee('Eletricista');
    }
}

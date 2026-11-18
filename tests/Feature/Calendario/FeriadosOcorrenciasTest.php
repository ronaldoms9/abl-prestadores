<?php

namespace Tests\Feature\Calendario;

use App\Enums\AbrangenciaFeriado;
use App\Enums\TipoOcorrencia;
use App\Models\Colaborador;
use App\Models\Feriado;
use App\Models\Obra;
use App\Models\Ocorrencia;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FeriadosOcorrenciasTest extends TestCase
{
    use RefreshDatabase;

    private function fecharMes(string $anoMes): void
    {
        $this->app->bind(CompetenciaFechada::class, fn () => new class($anoMes) implements CompetenciaFechada
        {
            public function __construct(private string $anoMes)
            {
            }

            public function fechada(CarbonInterface $data): bool
            {
                return $data->format('Y-m') === $this->anoMes;
            }
        });
    }

    public function test_feriado_nacional_nao_leva_obra_e_estadual_exige(): void
    {
        $this->actingAs($this->usuarioCom('feriado-list', 'feriado-gerir'));
        $obra = Obra::factory()->create();

        $this->postJson(route('feriado.store'), ['data' => '2026-09-07', 'descricao' => 'Independência', 'abrangencia' => 'nacional', 'obra_id' => $obra->id])->assertOk();
        $this->assertNull(Feriado::firstOrFail()->obra_id);

        $this->postJson(route('feriado.store'), ['data' => '2026-10-24', 'descricao' => 'Aniversário', 'abrangencia' => 'municipal'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Feriado municipal precisa da obra a que se aplica.']);

        $this->postJson(route('feriado.store'), ['data' => '2026-10-24', 'descricao' => 'Aniversário', 'abrangencia' => 'municipal', 'obra_id' => $obra->id])->assertOk();
        $this->assertSame($obra->id, Feriado::where('abrangencia', 'municipal')->firstOrFail()->obra_id);
        $this->assertTrue(Activity::where('event', 'feriado-criado')->exists());
    }

    public function test_recusa_feriado_repetido_mas_aceita_mesma_data_em_outra_obra(): void
    {
        $this->actingAs($this->usuarioCom('feriado-gerir'));
        [$a, $b] = [Obra::factory()->create(), Obra::factory()->create()];
        $dados = ['data' => '2026-10-24', 'descricao' => 'Aniversário', 'abrangencia' => 'municipal'];

        $this->postJson(route('feriado.store'), $dados + ['obra_id' => $a->id])->assertOk();
        $this->postJson(route('feriado.store'), $dados + ['obra_id' => $a->id])->assertStatus(422);
        $this->postJson(route('feriado.store'), $dados + ['obra_id' => $b->id])->assertOk();
    }

    public function test_edita_e_exclui_feriado(): void
    {
        $this->actingAs($this->usuarioCom('feriado-gerir'));
        $feriado = Feriado::factory()->create(['data' => '2026-09-07']);

        $this->postJson(route('feriado.store', $feriado->id), ['data' => '2026-09-07', 'descricao' => 'Sete de setembro', 'abrangencia' => 'nacional'])->assertOk();
        $this->assertSame('Sete de setembro', $feriado->fresh()->descricao);

        $this->deleteJson(route('feriado.delete', $feriado->id))->assertStatus(201);
        $this->assertSoftDeleted($feriado);
    }

    public function test_competencia_fechada_recusa_criar_alterar_e_excluir_feriado(): void
    {
        $feriado = Feriado::factory()->create(['data' => '2026-09-07']);
        $this->fecharMes('2026-09');
        $this->actingAs($this->usuarioCom('feriado-gerir'));

        $this->postJson(route('feriado.store'), ['data' => '2026-09-20', 'descricao' => 'Outro', 'abrangencia' => 'nacional'])->assertStatus(422);
        $this->postJson(route('feriado.store', $feriado->id), ['data' => '2026-10-07', 'descricao' => 'Mudou', 'abrangencia' => 'nacional'])->assertStatus(422);
        $this->deleteJson(route('feriado.delete', $feriado->id))->assertStatus(422);
        $this->assertNotSoftDeleted($feriado);
    }

    public function test_importa_nacionais_da_brasilapi_sem_duplicar(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response([
            ['date' => '2026-01-01', 'name' => 'Confraternização Universal', 'type' => 'national'],
            ['date' => '2026-09-07', 'name' => 'Independência do Brasil', 'type' => 'national'],
        ])]);
        Feriado::factory()->create(['data' => '2026-09-07', 'descricao' => 'Já cadastrado']);
        $this->actingAs($this->usuarioCom('feriado-gerir'));

        $this->postJson(route('feriado.importar'), ['ano' => 2026])->assertOk()->assertJsonPath('data.criados', 1)->assertJsonPath('data.existentes', 1);
        $this->postJson(route('feriado.importar'), ['ano' => 2026])->assertOk()->assertJsonPath('data.criados', 0);

        $this->assertSame(2, Feriado::count());
        $this->assertSame('Já cadastrado', Feriado::whereDate('data', '2026-09-07')->firstOrFail()->descricao);
    }

    public function test_falha_da_brasilapi_vira_aviso_sem_gravar(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response('erro', 500)]);
        $this->actingAs($this->usuarioCom('feriado-gerir'));

        $this->postJson(route('feriado.importar'), ['ano' => 2026])->assertStatus(422);
        $this->assertSame(0, Feriado::count());
    }

    public function test_lista_filtra_por_ano_e_mostra_a_obra(): void
    {
        $obra = Obra::factory()->create(['descricao' => 'Torre Jardins']);
        Feriado::factory()->create(['data' => '2026-10-24', 'descricao' => 'Aniversário da cidade', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $obra->id]);
        Feriado::factory()->create(['data' => '2025-09-07', 'descricao' => 'Feriado de 2025']);

        $this->actingAs($this->usuarioCom('feriado-list'))->get(route('feriado.index', ['ano' => 2026]))
            ->assertOk()->assertSee('Aniversário da cidade')->assertSee('Torre Jardins')->assertDontSee('Feriado de 2025');
    }

    public function test_sem_permissao_nao_gerencia_feriado_nem_ocorrencia(): void
    {
        $this->actingAs($this->usuarioCom('feriado-list', 'ocorrencia-list'));

        $this->get(route('feriado.index'))->assertOk();
        $this->get(route('ocorrencia.index'))->assertOk();
        $this->get(route('feriado.form'))->assertForbidden();
        $this->postJson(route('feriado.importar'), ['ano' => 2026])->assertForbidden();
        $this->get(route('ocorrencia.form'))->assertForbidden();
        $this->postJson(route('ocorrencia.store'), [])->assertForbidden();
    }

    public function test_lanca_edita_e_exclui_ocorrencia(): void
    {
        $this->actingAs($this->usuarioCom('ocorrencia-list', 'ocorrencia-gerir'));
        $colaborador = Colaborador::factory()->create();

        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'falta', 'inicio' => '2026-07-06', 'fim' => '2026-07-07', 'abona' => 1, 'observacao' => 'Atestado'])->assertOk();
        $ocorrencia = Ocorrencia::firstOrFail();
        $this->assertTrue($ocorrencia->abona);
        $this->assertSame(TipoOcorrencia::Falta, $ocorrencia->tipo);
        $this->assertTrue(Activity::where('event', 'ocorrencia-criado')->exists());

        $this->get(route('ocorrencia.index', ['mes' => '2026-07']))->assertOk()->assertSee('Atestado');
        $this->get(route('ocorrencia.index', ['mes' => '2026-08']))->assertOk()->assertDontSee('Atestado');

        $outro = Colaborador::factory()->create();
        $this->postJson(route('ocorrencia.store', $ocorrencia->id), ['colaborador_id' => $outro->id, 'tipo' => 'afastamento', 'inicio' => '2026-07-06', 'fim' => '2026-07-10'])->assertOk();
        $this->assertSame($colaborador->id, $ocorrencia->fresh()->colaborador_id);
        $this->assertSame(TipoOcorrencia::Afastamento, $ocorrencia->fresh()->tipo);
        $this->assertFalse($ocorrencia->fresh()->abona);

        $this->deleteJson(route('ocorrencia.delete', $ocorrencia->id))->assertStatus(201);
        $this->assertSoftDeleted($ocorrencia);
    }

    public function test_ocorrencia_valida_periodo(): void
    {
        $this->actingAs($this->usuarioCom('ocorrencia-gerir'));
        $colaborador = Colaborador::factory()->create();

        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'falta', 'inicio' => '2026-07-10', 'fim' => '2026-07-09'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'O fim não pode ser antes do início.']);
        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'outra', 'inicio' => '2026-07-10', 'fim' => '2026-07-10'])->assertStatus(422);
        $this->postJson(route('ocorrencia.store'), ['tipo' => 'falta', 'inicio' => '2026-07-10', 'fim' => '2026-07-10'])->assertStatus(422);
    }

    public function test_ocorrencia_em_competencia_fechada_e_recusada_em_qualquer_mes_do_periodo(): void
    {
        $colaborador = Colaborador::factory()->create();
        $existente = Ocorrencia::factory()->create(['colaborador_id' => $colaborador->id, 'inicio' => '2026-08-10', 'fim' => '2026-08-10']);
        $this->fecharMes('2026-08');
        $this->actingAs($this->usuarioCom('ocorrencia-gerir'));

        // Período que começa em julho (aberto) e termina em agosto (fechado).
        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'afastamento', 'inicio' => '2026-07-28', 'fim' => '2026-08-03'])->assertStatus(422);
        $this->postJson(route('ocorrencia.store', $existente->id), ['tipo' => 'falta', 'inicio' => '2026-09-01', 'fim' => '2026-09-01'])->assertStatus(422);
        $this->deleteJson(route('ocorrencia.delete', $existente->id))->assertStatus(422);
        $this->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'falta', 'inicio' => '2026-09-01', 'fim' => '2026-09-02'])->assertOk();
    }
}

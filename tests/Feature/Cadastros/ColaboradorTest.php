<?php

namespace Tests\Feature\Cadastros;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Empresa;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\VinculoSugerido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ColaboradorTest extends TestCase
{
    use RefreshDatabase;

    private function rh()
    {
        return $this->usuarioCom('colaborador-list', 'colaborador-create', 'colaborador-edit', 'pessoa-list');
    }

    private function dados(?PessoaFisica $pf = null, ?PessoaJuridica $pj = null, array $extra = []): array
    {
        return $extra + [
            'pessoa_fisica_id' => ($pf ?? PessoaFisica::factory()->create())->id,
            'pessoa_juridica_id' => ($pj ?? PessoaJuridica::factory()->create())->id,
        ];
    }

    public function test_cria_em_pre_admissao_com_cargo_empresa_e_obra(): void
    {
        $empresa = Empresa::factory()->create();
        $obra = Obra::factory()->create(['empresa_id' => $empresa->id]);
        $cargo = \App\Models\Cargo::factory()->create();

        $this->actingAs($this->rh())
            ->postJson(route('colaborador.store'), $this->dados(extra: ['cargo_id' => $cargo->id, 'empresa_id' => $empresa->id, 'obra_id' => $obra->id]))
            ->assertOk()->assertJson(['success' => true]);

        $c = Colaborador::firstOrFail();
        $this->assertSame(StatusColaborador::PreAdmissao, $c->status);
        $this->assertSame($obra->id, $c->obra_id);
        $this->assertTrue(Activity::where('event', 'colaborador-criado')->exists());
    }

    public function test_exige_pf_e_pj_existentes_e_ativas(): void
    {
        $rh = $this->rh();
        $this->actingAs($rh)->postJson(route('colaborador.store'), [])->assertStatus(422);
        $this->postJson(route('colaborador.store'), $this->dados(PessoaFisica::factory()->inativo()->create()))->assertStatus(422);
        $this->postJson(route('colaborador.store'), $this->dados(pj: PessoaJuridica::factory()->inativo()->create()))->assertStatus(422);
        $this->assertSame(0, Colaborador::count());
    }

    public function test_uma_pf_tem_um_colaborador_aberto_por_vez(): void
    {
        $pf = PessoaFisica::factory()->create();
        $rh = $this->actingAs($this->rh());

        $rh->postJson(route('colaborador.store'), $this->dados($pf))->assertOk();
        $rh->postJson(route('colaborador.store'), $this->dados($pf))->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'já é colaborador'));

        Colaborador::firstOrFail()->update(['status' => StatusColaborador::Desligado]);
        $rh->postJson(route('colaborador.store'), $this->dados($pf))->assertOk();

        $this->assertSame(2, Colaborador::where('pessoa_fisica_id', $pf->id)->count(), 'histórico com mais de uma PJ');
    }

    public function test_obra_precisa_ser_da_empresa(): void
    {
        $empresa = Empresa::factory()->create();
        $outraObra = Obra::factory()->create();

        $this->actingAs($this->rh())
            ->postJson(route('colaborador.store'), $this->dados(extra: ['empresa_id' => $empresa->id, 'obra_id' => $outraObra->id]))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'obra'));

        $this->postJson(route('colaborador.store'), $this->dados(extra: ['obra_id' => $outraObra->id]))->assertStatus(422);
    }

    public function test_vinculo_sugerido_da_planilha_e_marcado_como_utilizado(): void
    {
        $pf = PessoaFisica::factory()->create();
        $pj = PessoaJuridica::factory()->create();
        $outra = PessoaJuridica::factory()->create();
        $usado = VinculoSugerido::factory()->create(['pessoa_fisica_id' => $pf->id, 'pessoa_juridica_id' => $pj->id]);
        $naoUsado = VinculoSugerido::factory()->create(['pessoa_fisica_id' => $pf->id, 'pessoa_juridica_id' => $outra->id]);

        $this->actingAs($this->rh());
        $sugestoes = $this->getJson(route('colaborador.sugestoes', ['pessoa_fisica_id' => $pf->id]))->assertOk()->json();
        $this->assertCount(2, $sugestoes);

        $this->postJson(route('colaborador.store'), $this->dados($pf, $pj))->assertOk();

        $this->assertNotNull($usado->fresh()->utilizado_em);
        $this->assertNull($naoUsado->fresh()->utilizado_em);
        $this->assertCount(1, $this->getJson(route('colaborador.sugestoes', ['pessoa_fisica_id' => $pf->id]))->json());
    }

    public function test_edicao_troca_pj_cargo_obra_mas_nao_a_pessoa_fisica(): void
    {
        $pf = PessoaFisica::factory()->create();
        $c = Colaborador::factory()->create(['pessoa_fisica_id' => $pf->id]);
        $novaPj = PessoaJuridica::factory()->create();
        $outraPf = PessoaFisica::factory()->create();

        $this->actingAs($this->rh())
            ->postJson(route('colaborador.store', $c->id), ['pessoa_fisica_id' => $outraPf->id, 'pessoa_juridica_id' => $novaPj->id])
            ->assertOk();

        $c = $c->fresh();
        $this->assertSame($pf->id, $c->pessoa_fisica_id);
        $this->assertSame($novaPj->id, $c->pessoa_juridica_id);
        $this->assertTrue(Activity::where('event', 'colaborador-alterado')->exists());
    }

    public function test_rh_ve_todos_e_gestor_so_os_das_equipes_que_gere(): void
    {
        $gestor = $this->usuarioCom('colaborador-list', 'equipe-gerir');
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => now()->subMonth()]);

        $meu = Colaborador::factory()->create(['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Meu Colaborador'])->id]);
        $alheio = Colaborador::factory()->create(['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Colaborador Alheio'])->id]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $equipe->id, 'inicio' => now()->subMonth()]);

        $nomes = fn ($usuario) => collect($this->actingAs($usuario)->getJson(route('colaborador.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))
            ->map(fn ($l) => strip_tags(explode('<br>', $l['nome'])[0]))->sort()->values()->all();

        $this->assertSame(['Meu Colaborador'], $nomes($gestor));
        $this->assertSame(['Colaborador Alheio', 'Meu Colaborador'], $nomes($this->rh()));

        $this->actingAs($gestor)->get(route('colaborador.show', $alheio->id))->assertNotFound();
        $this->actingAs($gestor)->get(route('colaborador.show', $meu->id))->assertOk()->assertSee('Meu Colaborador');
    }

    public function test_lista_filtra_por_situacao_e_busca_por_cpf_nome_e_cnpj(): void
    {
        $pf = PessoaFisica::factory()->create(['nome' => 'Rita Souza', 'cpf' => '52998224725']);
        $pj = PessoaJuridica::factory()->create(['razao_social' => 'Alfa Obras Ltda', 'cnpj' => '11222333000181']);
        Colaborador::factory()->create(['pessoa_fisica_id' => $pf->id, 'pessoa_juridica_id' => $pj->id]);
        Colaborador::factory()->create(['status' => StatusColaborador::Desligado, 'pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Zé Antigo'])->id]);

        $rh = $this->rh();
        $total = fn (array $f) => count($this->actingAs($rh)->getJson(route('colaborador.index', $f), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'));

        $this->assertSame(1, $total([]), 'padrão: em pré-admissão e ativos');
        $this->assertSame(1, $total(['situacao' => 'desligado']));
        $this->assertSame(2, $total(['situacao' => 'todos']));
        $this->assertSame(1, $total(['busca' => 'rita']));
        $this->assertSame(1, $total(['busca' => '529.982']));
        $this->assertSame(1, $total(['busca' => 'alfa obras']));
        $this->assertSame(1, $total(['busca' => '11.222.333']));
        $this->assertSame(0, $total(['busca' => 'ninguém']));
    }

    public function test_incluir_e_retirar_da_equipe_pelo_colaborador(): void
    {
        $rh = $this->rh();
        $c = Colaborador::factory()->create();
        $equipe = Equipe::factory()->create();

        $this->actingAs($rh)->postJson(route('colaborador.equipe.store', $c->id), ['equipe_id' => $equipe->id, 'inicio' => '2026-02-01'])->assertOk();
        $vinculo = ColaboradorEquipe::firstOrFail();
        $this->postJson(route('colaborador.equipe.store', $c->id), ['equipe_id' => $equipe->id])->assertStatus(422);
        $this->postJson(route('colaborador.equipe.encerrar', $vinculo->id), ['fim' => '2026-03-01'])->assertOk();

        $this->assertSame('2026-03-01', $vinculo->fresh()->fim->toDateString());
        $this->assertTrue(Activity::where('event', 'colaborador-equipe-vinculado')->exists());
        $this->assertTrue(Activity::where('event', 'colaborador-equipe-encerrado')->exists());
        $this->get(route('colaborador.show', $c->id))->assertOk()->assertSee($equipe->nome);
    }

    public function test_busca_de_pj_para_o_formulario(): void
    {
        PessoaJuridica::factory()->create(['razao_social' => 'Construtora Horizonte', 'cnpj' => '11222333000181']);
        PessoaJuridica::factory()->inativo()->create(['razao_social' => 'Construtora Fechada']);

        $this->actingAs($this->usuarioCom('colaborador-create'));
        $this->assertCount(1, $this->getJson(route('pessoa-juridica.buscar', ['q' => 'construtora']))->assertOk()->json());
        $this->assertCount(1, $this->getJson(route('pessoa-juridica.buscar', ['q' => '11.222']))->json());
    }
}

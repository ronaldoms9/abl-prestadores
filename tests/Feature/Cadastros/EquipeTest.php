<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Equipes\AdicionarGestor;
use App\Actions\Equipes\EncerrarVinculo;
use App\Actions\Equipes\VincularColaborador;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\PessoaFisica;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EquipeTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return $this->usuarioCom('equipe-list', 'equipe-gerir');
    }

    public function test_crud_da_equipe_com_nome_unico_e_auditoria(): void
    {
        $rh = $this->usuarioCom('equipe-list', 'equipe-create', 'equipe-edit');

        $this->actingAs($rh)->postJson(route('equipe.store'), ['nome' => 'Elétrica', 'descricao' => 'Instalações'])->assertOk();
        $equipe = Equipe::firstOrFail();
        $this->assertTrue($equipe->ativo);
        $this->assertTrue(Activity::where('event', 'equipe-criado')->exists());

        $this->postJson(route('equipe.store'), ['nome' => 'Elétrica'])->assertStatus(422);
        $this->postJson(route('equipe.store', $equipe->id), ['nome' => 'Elétrica', 'descricao' => 'Novo', 'ativo' => 0])->assertOk();
        $this->assertFalse($equipe->fresh()->ativo);

        $this->deleteJson(route('equipe.delete', $equipe->id))->assertStatus(201);
        $this->assertSoftDeleted($equipe);
    }

    public function test_nao_exclui_equipe_com_vinculo_vigente(): void
    {
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id]);

        $this->actingAs($this->usuarioCom('equipe-edit'))
            ->deleteJson(route('equipe.delete', $equipe->id))
            ->assertStatus(400)
            ->assertJson(['success' => false]);
        $this->assertNotSoftDeleted($equipe);
    }

    public function test_so_entra_como_gestor_usuario_ativo_com_equipe_gerir(): void
    {
        $equipe = Equipe::factory()->create();
        $acao = app(AdicionarGestor::class);

        $semPermissao = $this->usuarioCom('equipe-list');
        $inativo = $this->gestor();
        $inativo->update(['ativo' => false]);

        foreach ([$semPermissao, $inativo] as $usuario) {
            try {
                $acao->executar($equipe, $usuario);
                $this->fail('Deveria recusar.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, EquipeGestor::count());
        $acao->executar($equipe, $this->gestor());
        $this->assertSame(1, EquipeGestor::count());
    }

    public function test_gestor_nao_fica_duplicado_e_a_equipe_pode_ter_varios(): void
    {
        $equipe = Equipe::factory()->create();
        $a = $this->gestor();
        $b = $this->gestor();
        $acao = app(AdicionarGestor::class);

        $acao->executar($equipe, $a);
        $acao->executar($equipe, $b);

        $this->expectException(ValidationException::class);
        $acao->executar($equipe, $a);
    }

    public function test_gestor_pode_gerir_varias_equipes_e_voltar_depois_de_encerrado(): void
    {
        $a = Equipe::factory()->create();
        $b = Equipe::factory()->create();
        $gestor = $this->gestor();
        $acao = app(AdicionarGestor::class);

        $vinculo = $acao->executar($a, $gestor, Carbon::parse('2026-01-01'));
        $acao->executar($b, $gestor, Carbon::parse('2026-01-01'));
        app(EncerrarVinculo::class)->executar($vinculo, Carbon::parse('2026-03-01'));

        $acao->executar($a, $gestor, Carbon::parse('2026-04-01'));
        $this->assertSame(3, EquipeGestor::count());
    }

    public function test_encerrar_valida_data_e_nao_encerra_duas_vezes(): void
    {
        $vinculo = EquipeGestor::factory()->create(['inicio' => '2026-05-10']);
        $encerrar = app(EncerrarVinculo::class);

        try {
            $encerrar->executar($vinculo, Carbon::parse('2026-05-09'));
            $this->fail('Fim anterior ao início.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $encerrar->executar($vinculo, Carbon::parse('2026-06-01'));
        $this->assertSame('2026-06-01', $vinculo->fresh()->fim->toDateString());

        $this->expectException(ValidationException::class);
        $encerrar->executar($vinculo->fresh(), Carbon::parse('2026-07-01'));
    }

    public function test_colaborador_em_varias_equipes_ou_nenhuma_sem_repetir_a_mesma(): void
    {
        $colaborador = Colaborador::factory()->create();
        $a = Equipe::factory()->create();
        $b = Equipe::factory()->create();
        $acao = app(VincularColaborador::class);

        $acao->executar($colaborador, $a, Carbon::parse('2026-01-01'));
        $acao->executar($colaborador, $b, Carbon::parse('2026-01-01'));

        $this->assertSame(2, $colaborador->equipesVigentesEm('2026-02-01')->count());
        $this->assertSame(0, $colaborador->equipesVigentesEm('2025-12-31')->count());

        $this->expectException(ValidationException::class);
        $acao->executar($colaborador, $a);
    }

    public function test_nao_vincula_a_equipe_inativa(): void
    {
        $this->expectException(ValidationException::class);
        app(VincularColaborador::class)->executar(Colaborador::factory()->create(), Equipe::factory()->create(['ativo' => false]));
    }

    public function test_gestores_vigentes_em_uma_data(): void
    {
        $equipe = Equipe::factory()->create();
        $antigo = $this->gestor();
        $atual = $this->gestor();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $antigo->id, 'inicio' => '2026-01-01', 'fim' => '2026-06-01']);
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $atual->id, 'inicio' => '2026-06-01', 'fim' => null]);

        $em = fn (string $dia) => $equipe->gestoresVigentesEm($dia)->pluck('id')->all();

        $this->assertSame([$antigo->id], $em('2026-05-31'));
        $this->assertSame([$atual->id], $em('2026-06-01'));
    }

    public function test_gestor_ve_so_colaboradores_das_equipes_que_gere_hoje(): void
    {
        $gestor = $this->gestor();
        $minha = Equipe::factory()->create();
        $outra = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $minha->id, 'user_id' => $gestor->id, 'inicio' => '2026-01-01']);

        $meu = Colaborador::factory()->create();
        $dosOutros = Colaborador::factory()->create();
        $ex = Colaborador::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $meu->id, 'equipe_id' => $minha->id, 'inicio' => '2026-01-01']);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $dosOutros->id, 'equipe_id' => $outra->id, 'inicio' => '2026-01-01']);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $ex->id, 'equipe_id' => $minha->id, 'inicio' => '2026-01-01', 'fim' => '2026-03-01']);

        $ids = fn (string $dia) => $gestor->colaboradoresGeridosEm($dia)->pluck('colaboradores.id')->all();

        $this->assertSame([$meu->id], $ids('2026-06-01'));
        $this->assertEqualsCanonicalizing([$meu->id, $ex->id], $ids('2026-02-01'));
        $this->assertSame([], $ids('2025-12-01'));
    }

    public function test_gestor_ve_so_as_equipes_que_gere_e_rh_ve_todas(): void
    {
        $gestor = $this->gestor();
        $minha = Equipe::factory()->create(['nome' => 'Minha equipe']);
        Equipe::factory()->create(['nome' => 'Equipe alheia']);
        EquipeGestor::factory()->create(['equipe_id' => $minha->id, 'user_id' => $gestor->id, 'inicio' => now()->subDay()]);

        $this->assertSame(['Minha equipe'], Equipe::visiveisPara($gestor)->pluck('nome')->all());
        $this->assertCount(2, Equipe::visiveisPara($this->usuarioCom('equipe-list', 'equipe-edit'))->get());
        $this->assertCount(2, Equipe::visiveisPara($this->usuarioCom('equipe-list'))->get(), 'financeiro consulta tudo');

        $this->actingAs($gestor)->get(route('equipe.show', Equipe::where('nome', 'Equipe alheia')->value('id')))->assertNotFound();
        $this->actingAs($gestor)->get(route('equipe.show', $minha->id))->assertOk()->assertSee('Minha equipe');
    }

    public function test_tela_de_detalhe_pelo_rh_lista_gestores_e_colaboradores(): void
    {
        $rh = $this->usuarioCom('equipe-list', 'equipe-edit');
        $equipe = Equipe::factory()->create();
        $gestor = $this->gestor();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id]);
        $colaborador = Colaborador::factory()->create(['pessoa_fisica_id' => PessoaFisica::factory()->create(['nome' => 'Ana Pedreira'])->id]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $colaborador->id, 'equipe_id' => $equipe->id]);

        $this->actingAs($rh)->get(route('equipe.show', $equipe->id))
            ->assertOk()
            ->assertSee($gestor->name)
            ->assertSee('Ana Pedreira');
    }

    public function test_adicionar_e_retirar_gestor_pela_tela_com_auditoria(): void
    {
        $rh = $this->usuarioCom('equipe-list', 'equipe-edit');
        $equipe = Equipe::factory()->create();
        $gestor = $this->gestor();

        $this->actingAs($rh)->postJson(route('equipe.gestor.store', $equipe->id), ['user_id' => $gestor->id, 'inicio' => '2026-02-01'])->assertOk();
        $vinculo = EquipeGestor::firstOrFail();
        $this->assertTrue(Activity::where('event', 'equipe-gestor-adicionado')->exists());

        $this->postJson(route('equipe.gestor.store', $equipe->id), ['user_id' => $gestor->id])->assertStatus(422);
        $this->postJson(route('equipe.gestor.encerrar', $vinculo->id), ['fim' => '2026-01-01'])->assertStatus(422);
        $this->postJson(route('equipe.gestor.encerrar', $vinculo->id), ['fim' => '2026-03-01'])->assertOk();
        $this->assertSame('2026-03-01', $vinculo->fresh()->fim->toDateString());
        $this->assertTrue(Activity::where('event', 'equipe-gestor-encerrado')->exists());
    }
}

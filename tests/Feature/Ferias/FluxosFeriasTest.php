<?php

namespace Tests\Feature\Ferias;

use App\Actions\Ferias\DecidirFerias;
use App\Actions\Ferias\SolicitarFerias;
use App\Enums\DecisaoFerias;
use App\Enums\EscopoFluxoFerias;
use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAprovadorFerias;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\FeriasFluxo;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\User;
use App\Permission;
use App\Support\Ferias\AprovadoresFerias;
use App\Support\Ferias\FluxoFerias;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fluxos de aprovação de férias por colaborador, equipe e função (E4, fatia 9). */
class FluxosFeriasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 09:00:00');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nivel(FeriasFluxo $fluxo, int $ordem, TipoAprovadorFerias $tipo, ?User $usuario = null, ?string $permissao = null): FeriasNivel
    {
        return FeriasNivel::create([
            'fluxo_id' => $fluxo->id, 'ordem' => $ordem, 'tipo_aprovador' => $tipo, 'ativo' => true,
            'user_id' => $usuario?->id,
            'permission_id' => $permissao ? Permission::firstOrCreate(['name' => $permissao], ['display_name' => $permissao])->id : null,
        ]);
    }

    private function fluxo(EscopoFluxoFerias $escopo, string $nome = 'Fluxo', array $alvo = []): FeriasFluxo
    {
        return FeriasFluxo::create(['nome' => $nome, 'escopo' => $escopo, 'ativo' => true] + $alvo);
    }

    /** Fluxo padrão: só o gestor da equipe. */
    private function padraoSoGestor(): FeriasFluxo
    {
        $padrao = FeriasFluxo::padrao();
        $this->nivel($padrao, 1, TipoAprovadorFerias::GestorEquipe);

        return $padrao;
    }

    private function colaborador(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-03-02']);
    }

    private function naEquipe(Colaborador $c, ?Equipe $equipe = null, ?User $gestor = null): array
    {
        $equipe ??= Equipe::factory()->create();
        ColaboradorEquipe::factory()->create(['colaborador_id' => $c->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-03-02', 'fim' => null]);
        $gestor ??= User::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-03-02', 'fim' => null]);

        return [$equipe, $gestor];
    }

    private function solicitar(Colaborador $c, string $inicio = '2026-08-03', string $fim = '2026-08-12'): FeriasSolicitacao
    {
        $periodo = FeriasPeriodo::query()->where('colaborador_id', $c->id)->first() ?? FeriasPeriodo::factory()->create(['colaborador_id' => $c->id]);

        return app(SolicitarFerias::class)->executar($c, $periodo, Carbon::parse($inicio), Carbon::parse($fim), null)['solicitacao'];
    }

    private function decidir(FeriasSolicitacao $s, User $u): FeriasSolicitacao
    {
        return app(DecidirFerias::class)->executar($s->fresh(), $u, DecisaoFerias::Aprovada);
    }

    private function quemAprova(FeriasSolicitacao $s): array
    {
        return app(AprovadoresFerias::class)->resolver($s->fresh()->load('colaborador'), now())['usuarios']->pluck('id')->all();
    }

    // ---- Os três exemplos do pedido ---------------------------------------

    public function test_colaborador_comum_solicita_e_o_gestor_aprova_e_so(): void
    {
        $this->padraoSoGestor();
        $c = $this->colaborador();
        [, $gestor] = $this->naEquipe($c);

        $s = $this->solicitar($c);
        $this->assertSame([$gestor->id], $this->quemAprova($s));

        $s = $this->decidir($s, $gestor);

        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $s->status);
    }

    public function test_equipe_x_passa_pelo_gestor_e_depois_pelo_diretor(): void
    {
        $this->padraoSoGestor();
        $diretor = User::factory()->create();
        $equipeX = Equipe::factory()->create();
        $fluxoX = $this->fluxo(EscopoFluxoFerias::Equipe, 'Equipe X', ['equipe_id' => $equipeX->id]);
        $this->nivel($fluxoX, 1, TipoAprovadorFerias::GestorEquipe);
        $this->nivel($fluxoX, 2, TipoAprovadorFerias::Usuario, $diretor);

        $c = $this->colaborador();
        [, $gestor] = $this->naEquipe($c, $equipeX);

        $s = $this->solicitar($c);
        $this->assertSame($fluxoX->id, $s->fluxo_id);
        $this->assertSame([$gestor->id], $this->quemAprova($s));

        $s = $this->decidir($s, $gestor);
        $this->assertSame(StatusSolicitacaoFerias::Pendente, $s->status, 'Falta o diretor');
        $this->assertSame([$diretor->id], $this->quemAprova($s));

        $s = $this->decidir($s, $diretor);
        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $s->status);
    }

    public function test_gerente_so_precisa_do_diretor(): void
    {
        $this->padraoSoGestor();
        $gerente = Cargo::factory()->create(['nome' => 'Gerente']);
        $perfilDiretor = $this->usuarioCom('ferias-aprovar-diretor');
        $fluxo = $this->fluxo(EscopoFluxoFerias::Cargo, 'Gerentes', ['cargo_id' => $gerente->id]);
        $this->nivel($fluxo, 1, TipoAprovadorFerias::Permissao, null, 'ferias-aprovar-diretor');

        $y = $this->colaborador(['cargo_id' => $gerente->id]);
        [, $gestorDaEquipe] = $this->naEquipe($y);

        $s = $this->solicitar($y);

        $this->assertSame([$perfilDiretor->id], $this->quemAprova($s), 'O gestor da equipe não decide: o fluxo do gerente é só o diretor');
        $this->assertNotContains($gestorDaEquipe->id, $this->quemAprova($s));
        $this->assertSame(StatusSolicitacaoFerias::Aprovada, $this->decidir($s, $perfilDiretor)->status);
    }

    // ---- Precedência -------------------------------------------------------

    public function test_vale_o_fluxo_mais_especifico(): void
    {
        $padrao = $this->padraoSoGestor();
        $equipe = Equipe::factory()->create();
        $cargo = Cargo::factory()->create();
        $c = $this->colaborador(['cargo_id' => $cargo->id]);
        $this->naEquipe($c, $equipe);

        $porCargo = $this->fluxo(EscopoFluxoFerias::Cargo, 'Cargo', ['cargo_id' => $cargo->id]);
        $porEquipe = $this->fluxo(EscopoFluxoFerias::Equipe, 'Equipe', ['equipe_id' => $equipe->id]);
        $equipeCargo = $this->fluxo(EscopoFluxoFerias::EquipeCargo, 'Equipe e cargo', ['equipe_id' => $equipe->id, 'cargo_id' => $cargo->id]);
        $individual = $this->fluxo(EscopoFluxoFerias::Colaborador, 'Individual', ['colaborador_id' => $c->id]);
        foreach ([$porCargo, $porEquipe, $equipeCargo, $individual] as $f) {
            $this->nivel($f, 1, TipoAprovadorFerias::GestorEquipe);
        }

        $resolve = fn () => app(FluxoFerias::class)->paraColaborador($c->fresh(), now())->id;

        $this->assertSame($individual->id, $resolve());
        $individual->update(['ativo' => false]);
        $this->assertSame($equipeCargo->id, $resolve());
        $equipeCargo->delete();
        $this->assertSame($porEquipe->id, $resolve());
        $porEquipe->delete();
        $this->assertSame($porCargo->id, $resolve());
        $porCargo->delete();
        $this->assertSame($padrao->id, $resolve());
    }

    public function test_funcao_so_vale_na_equipe_do_fluxo(): void
    {
        $padrao = $this->padraoSoGestor();
        $equipeX = Equipe::factory()->create();
        $gerente = Cargo::factory()->create();
        $fluxo = $this->fluxo(EscopoFluxoFerias::EquipeCargo, 'Gerentes da X', ['equipe_id' => $equipeX->id, 'cargo_id' => $gerente->id]);
        $this->nivel($fluxo, 1, TipoAprovadorFerias::GestorEquipe);

        $gerenteNaX = $this->colaborador(['cargo_id' => $gerente->id]);
        $this->naEquipe($gerenteNaX, $equipeX);
        $gerenteNaY = $this->colaborador(['cargo_id' => $gerente->id]);
        $this->naEquipe($gerenteNaY);
        $outroNaX = $this->colaborador(['cargo_id' => Cargo::factory()->create()->id]);
        $this->naEquipe($outroNaX, $equipeX);

        $f = app(FluxoFerias::class);
        $this->assertSame($fluxo->id, $f->paraColaborador($gerenteNaX, now())->id);
        $this->assertSame($padrao->id, $f->paraColaborador($gerenteNaY, now())->id);
        $this->assertSame($padrao->id, $f->paraColaborador($outroNaX, now())->id);
    }

    public function test_empate_vence_o_fluxo_mais_rigoroso(): void
    {
        $padrao = $this->padraoSoGestor();
        $c = $this->colaborador();
        $e1 = Equipe::factory()->create();
        $e2 = Equipe::factory()->create();
        $this->naEquipe($c, $e1);
        $this->naEquipe($c, $e2);
        $curto = $this->fluxo(EscopoFluxoFerias::Equipe, 'Curto', ['equipe_id' => $e1->id]);
        $this->nivel($curto, 1, TipoAprovadorFerias::GestorEquipe);
        $longo = $this->fluxo(EscopoFluxoFerias::Equipe, 'Longo', ['equipe_id' => $e2->id]);
        $this->nivel($longo, 1, TipoAprovadorFerias::GestorEquipe);
        $this->nivel($longo, 2, TipoAprovadorFerias::Permissao, null, 'ferias-aprovar-rh');

        $this->assertSame($longo->id, app(FluxoFerias::class)->paraColaborador($c, now())->id);
        $this->assertNotSame($padrao->id, $longo->id);
    }

    public function test_fluxo_sem_nivel_ativo_nao_conta(): void
    {
        $padrao = $this->padraoSoGestor();
        $cargo = Cargo::factory()->create();
        $c = $this->colaborador(['cargo_id' => $cargo->id]);
        $vazio = $this->fluxo(EscopoFluxoFerias::Cargo, 'Vazio', ['cargo_id' => $cargo->id]);

        $this->assertSame($padrao->id, app(FluxoFerias::class)->paraColaborador($c, now())->id);
        $this->assertSame(0, $vazio->niveis()->count());
    }

    // ---- Congelamento e equipe --------------------------------------------

    public function test_mudar_o_fluxo_nao_muda_o_caminho_da_solicitacao_pendente(): void
    {
        $padrao = $this->padraoSoGestor();
        $c = $this->colaborador();
        [, $gestor] = $this->naEquipe($c);
        $s = $this->solicitar($c);

        $this->nivel($padrao, 2, TipoAprovadorFerias::Permissao, null, 'ferias-aprovar-rh');
        $novoFluxo = $this->fluxo(EscopoFluxoFerias::Colaborador, 'Só para ele', ['colaborador_id' => $c->id]);
        $this->nivel($novoFluxo, 1, TipoAprovadorFerias::Usuario, User::factory()->create());

        $this->assertSame($padrao->id, $s->fresh()->fluxo_id, 'A solicitação continua presa ao fluxo em que nasceu');
        $this->assertSame([$gestor->id], $this->quemAprova($s));
        $this->assertSame($novoFluxo->id, $this->solicitar($c, '2026-09-07', '2026-09-11')->fluxo_id, 'A solicitação nova já segue o fluxo novo');
    }

    public function test_fluxo_de_equipe_so_chama_os_gestores_daquela_equipe(): void
    {
        $this->padraoSoGestor();
        $c = $this->colaborador();
        [$equipeA, $gestorA] = $this->naEquipe($c);
        [, $gestorB] = $this->naEquipe($c);
        $fluxo = $this->fluxo(EscopoFluxoFerias::Equipe, 'Só a A', ['equipe_id' => $equipeA->id]);
        $this->nivel($fluxo, 1, TipoAprovadorFerias::GestorEquipe);

        $s = $this->solicitar($c);

        $this->assertSame([$gestorA->id], $this->quemAprova($s));
        $this->assertNotContains($gestorB->id, $this->quemAprova($s));
    }

    public function test_nivel_de_usuario_inativo_e_pulado(): void
    {
        $this->padraoSoGestor();
        $diretor = User::factory()->create(['ativo' => false]);
        $cargo = Cargo::factory()->create();
        $fluxo = $this->fluxo(EscopoFluxoFerias::Cargo, 'Gerentes', ['cargo_id' => $cargo->id]);
        $this->nivel($fluxo, 1, TipoAprovadorFerias::Usuario, $diretor);
        $rh = $this->usuarioCom('ferias-aprovar-qualquer');
        $c = $this->colaborador(['cargo_id' => $cargo->id]);

        $s = $this->solicitar($c);

        $this->assertSame([$rh->id], $this->quemAprova($s), 'Sem aprovador no nível, a solicitação escala a quem aprova qualquer férias');
    }

    public function test_lote_resolve_igual_ao_individual(): void
    {
        $this->padraoSoGestor();
        $diretor = User::factory()->create();
        $equipeX = Equipe::factory()->create();
        $fluxoX = $this->fluxo(EscopoFluxoFerias::Equipe, 'X', ['equipe_id' => $equipeX->id]);
        $this->nivel($fluxoX, 1, TipoAprovadorFerias::GestorEquipe);
        $this->nivel($fluxoX, 2, TipoAprovadorFerias::Usuario, $diretor);
        $a = $this->colaborador();
        $this->naEquipe($a, $equipeX);
        $b = $this->colaborador();
        $this->naEquipe($b);
        $solicitacoes = collect([$this->solicitar($a), $this->solicitar($b)])->map(fn ($s) => $s->fresh()->load('colaborador'));

        $servico = app(AprovadoresFerias::class);
        $lote = $servico->carregarLote($solicitacoes, now());

        foreach ($solicitacoes as $s) {
            $this->assertEquals(
                $servico->resolver($s, now())['usuarios']->pluck('id')->all(),
                $servico->resolver($s, now(), $lote)['usuarios']->pluck('id')->all(),
            );
        }
    }

    // ---- Tela ---------------------------------------------------------------

    public function test_tela_cria_fluxo_e_niveis_e_valida(): void
    {
        $this->padraoSoGestor();
        $admin = $this->usuarioCom('ferias-niveis-configurar');
        $this->actingAs($admin);
        $equipe = Equipe::factory()->create();
        $diretor = User::factory()->create();

        $this->get(route('ferias-nivel.index'))->assertOk()->assertSee('Fluxo padrão');
        $this->get(route('ferias-fluxo.form'))->assertOk();

        $this->postJson(route('ferias-fluxo.store'), ['nome' => 'Equipe', 'escopo' => 'equipe'])->assertStatus(422);
        $this->postJson(route('ferias-fluxo.store'), ['nome' => 'Padrão 2', 'escopo' => 'padrao'])->assertStatus(422);
        $this->postJson(route('ferias-fluxo.store'), ['nome' => 'Equipe X', 'escopo' => 'equipe', 'equipe_id' => $equipe->id])->assertOk();
        $this->postJson(route('ferias-fluxo.store'), ['nome' => 'Outra', 'escopo' => 'equipe', 'equipe_id' => $equipe->id])->assertStatus(422);

        $fluxo = FeriasFluxo::where('escopo', 'equipe')->firstOrFail();
        $this->postJson(route('ferias-nivel.store'), ['fluxo_id' => $fluxo->id, 'ordem' => 1, 'tipo_aprovador' => 'gestor_equipe'])->assertOk();
        $this->postJson(route('ferias-nivel.store'), ['fluxo_id' => $fluxo->id, 'ordem' => 1, 'tipo_aprovador' => 'gestor_equipe'])->assertStatus(422);
        $this->postJson(route('ferias-nivel.store'), ['fluxo_id' => $fluxo->id, 'ordem' => 2, 'tipo_aprovador' => 'usuario'])->assertStatus(422);
        $this->postJson(route('ferias-nivel.store'), ['fluxo_id' => $fluxo->id, 'ordem' => 2, 'tipo_aprovador' => 'usuario', 'user_id' => $diretor->id])->assertOk();

        $this->assertSame(2, $fluxo->niveis()->count());
        $this->get(route('ferias-nivel.index'))->assertOk()->assertSee('Equipe X')->assertSee($diretor->name);
        $this->get(route('ferias-nivel.form').'?fluxo='.$fluxo->id)->assertOk();
    }

    public function test_fluxo_padrao_nao_pode_ser_excluido_e_exclusao_remove_os_niveis(): void
    {
        $padrao = $this->padraoSoGestor();
        $this->actingAs($this->usuarioCom('ferias-niveis-configurar'));
        $fluxo = $this->fluxo(EscopoFluxoFerias::Cargo, 'Gerentes', ['cargo_id' => Cargo::factory()->create()->id]);
        $this->nivel($fluxo, 1, TipoAprovadorFerias::GestorEquipe);

        $this->deleteJson(route('ferias-fluxo.delete', $padrao->id))->assertStatus(422);
        $this->deleteJson(route('ferias-fluxo.delete', $fluxo->id))->assertStatus(201);

        $this->assertSoftDeleted($fluxo);
        $this->assertSame(0, FeriasNivel::where('fluxo_id', $fluxo->id)->count());
        $this->postJson(route('ferias-fluxo.store', $padrao->id), ['nome' => 'Padrão da casa'])->assertOk();
        $this->assertSame('Padrão da casa', $padrao->fresh()->nome);
    }

    public function test_so_quem_configura_acessa_a_tela(): void
    {
        $this->actingAs($this->usuarioCom('ferias-list'));

        $this->get(route('ferias-fluxo.form'))->assertForbidden();
        $this->postJson(route('ferias-fluxo.store'), [])->assertForbidden();
    }
}

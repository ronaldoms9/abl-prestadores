<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use App\Support\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaTelaTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    private User $maria;

    private User $joao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = $this->usuarioCom('auditoria-list');
        $this->maria = User::factory()->create(['name' => 'Maria']);
        $this->joao = User::factory()->create(['name' => 'João']);
        Activity::query()->delete();
    }

    public function test_sem_permissao_recebe_403_na_lista_e_no_detalhe(): void
    {
        $this->registrar('login', $this->maria);
        $id = Activity::value('id');
        $semPermissao = $this->usuarioCom('user-list');

        $this->actingAs($semPermissao)->get(route('auditoria.index'))->assertForbidden();
        $this->actingAs($semPermissao)->get(route('auditoria.show', $id))->assertForbidden();
    }

    public function test_tela_abre_com_os_filtros(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertSee('filtros-auditoria')
            ->assertSee('Falha de login')
            ->assertSee(now()->subDays(6)->toDateString());
    }

    public function test_filtra_por_usuario(): void
    {
        $this->registrar('login', $this->maria);
        $this->registrar('login', $this->joao);

        $linhas = $this->listar(['usuario' => $this->maria->id])->json('data');

        $this->assertSame(['Maria'], array_column($linhas, 'usuario'));
    }

    public function test_filtra_por_acao(): void
    {
        $this->registrar('login', $this->maria);
        $this->registrar('logout', $this->maria);

        $linhas = $this->listar(['acao' => 'logout'])->json('data');

        $this->assertSame(['Logout'], array_column($linhas, 'acao'));
    }

    public function test_filtra_por_periodo_e_o_padrao_e_de_sete_dias(): void
    {
        $this->registrar('login', $this->maria, now()->subDays(10));
        $this->registrar('login', $this->joao, now()->subDays(3));
        $this->registrar('logout', $this->joao, now());

        $this->assertCount(2, $this->listar()->json('data'));

        $periodo = $this->listar(['de' => now()->subDays(12)->toDateString(), 'ate' => now()->subDays(5)->toDateString()]);
        $this->assertSame(['Maria'], array_column($periodo->json('data'), 'usuario'));

        $this->assertCount(3, $this->listar(['de' => ''])->json('data'));
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        $this->listar(['acao' => 'inventada'])->assertUnprocessable();
    }

    public function test_colunas_descrevem_usuario_objeto_e_alteracoes(): void
    {
        $this->actingAs($this->auditor);
        $this->maria->update(['email' => 'maria@abl.com.br', 'ativo' => false]);
        Auditoria::registrar('login-falhou', dados: ['email' => 'quem@example.com']);

        $linhas = collect($this->listar()->json('data'))->keyBy('acao');

        $this->assertSame(e($this->auditor->name), $linhas['Usuário alterado']['usuario']);
        $this->assertSame('Usuário · Maria', $linhas['Usuário alterado']['objeto']);
        $this->assertSame('e-mail, ativo', $linhas['Usuário alterado']['alteracoes']);
        $this->assertSame('quem@example.com', $linhas['Falha de login']['usuario']);
    }

    public function test_detalhe_mostra_antes_e_depois_e_as_permissoes(): void
    {
        $this->actingAs($this->auditor);
        $this->maria->update(['name' => 'Maria Souza']);
        $alteracao = Activity::latest('id')->first();

        $perfil = Role::create(['name' => 'rh', 'display_name' => 'RH']);
        Auditoria::registrar('perfil-permissoes-alteradas', $perfil, ['incluidas' => ['Ver obras'], 'removidas' => ['Ver empresas']]);
        $permissoes = Activity::latest('id')->first();

        $this->get(route('auditoria.show', $alteracao->id))
            ->assertOk()
            ->assertSeeInOrder(['nome', 'Maria', 'Maria Souza']);

        $this->get(route('auditoria.show', $permissoes->id))
            ->assertOk()
            ->assertSeeInOrder(['Permissões incluídas', 'Ver obras', 'Permissões removidas', 'Ver empresas']);
    }

    public function test_objeto_excluido_continua_identificado(): void
    {
        $this->actingAs($this->auditor);
        $this->maria->delete();

        $linha = $this->listar(['acao' => 'usuario-excluido'])->json('data.0');

        $this->assertSame('Usuário · Maria', $linha['objeto']);
    }

    private function registrar(string $acao, User $autor, $quando = null): void
    {
        Auditoria::registrar($acao, autor: $autor);

        if ($quando) {
            Activity::latest('id')->first()->forceFill(['created_at' => $quando])->save();
        }
    }

    private function listar(array $filtros = []): TestResponse
    {
        return $this->actingAs($this->auditor)
            ->getJson(route('auditoria.index', $filtros), ['X-Requested-With' => 'XMLHttpRequest']);
    }
}

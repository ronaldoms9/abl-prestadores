<?php

namespace Tests\Feature;

use App\Http\Middleware\UsuarioAtivo;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioAtivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_inativo_nao_entra_e_recebe_a_mensagem_de_credencial_errada(): void
    {
        $user = User::factory()->inativo()->create();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_usuario_ativo_entra(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_usuario_desativado_com_sessao_aberta_e_desconectado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('home'))->assertOk();

        $user->update(['ativo' => false]);

        $this->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => UsuarioAtivo::MENSAGEM]);
        $this->assertGuest();
    }

    public function test_requisicao_ajax_de_usuario_desativado_recebe_401(): void
    {
        $user = User::factory()->inativo()->create();

        $this->actingAs($user)->getJson(route('home'))->assertUnauthorized();
    }

    public function test_admin_nao_consegue_se_desativar(): void
    {
        $admin = $this->usuarioCom('user-edit');

        $this->actingAs($admin)
            ->postJson(route('user.store', $admin->id), $this->dados($admin, ['ativo' => '0']))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Você não pode desativar o próprio acesso.');

        $this->assertTrue($admin->fresh()->ativo);
    }

    public function test_admin_nao_consegue_se_excluir(): void
    {
        $admin = $this->usuarioCom('user-delete');

        $this->actingAs($admin)
            ->deleteJson(route('user.delete', $admin->id))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Você não pode excluir o próprio usuário.');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_admin_desativa_e_reativa_outro_usuario(): void
    {
        $admin = $this->usuarioCom('user-edit');
        $alvo = User::factory()->create();

        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), $this->dados($alvo, ['ativo' => '0']))->assertOk();
        $this->assertFalse($alvo->fresh()->ativo);

        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), $this->dados($alvo, ['ativo' => '1']))->assertOk();
        $this->assertTrue($alvo->fresh()->ativo);
    }

    public function test_edicao_sem_o_campo_ativo_mantem_a_situacao(): void
    {
        $admin = $this->usuarioCom('user-edit');
        $alvo = User::factory()->inativo()->create();

        $this->actingAs($admin)->postJson(route('user.store', $alvo->id), $this->dados($alvo))->assertOk();

        $this->assertFalse($alvo->fresh()->ativo);
    }

    public function test_exclusao_e_logica(): void
    {
        $alvo = User::factory()->create();

        $this->actingAs($this->usuarioCom('user-delete'))
            ->deleteJson(route('user.delete', $alvo->id))
            ->assertStatus(201);

        $this->assertSoftDeleted($alvo);
    }

    public function test_listagem_filtra_por_situacao(): void
    {
        $admin = $this->usuarioCom('user-list');
        $inativo = User::factory()->inativo()->create();

        $resposta = $this->actingAs($admin)
            ->getJson(route('user.index', ['situacao' => 'inativos']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertSame([$inativo->email], array_column($resposta->json('data'), 'email'));
    }

    private function dados(User $user, array $extra = []): array
    {
        return $extra + [
            'nome' => $user->name,
            'email' => $user->email,
            'grupo_acesso' => Role::firstOrCreate(['name' => 'qualquer'], ['display_name' => 'Qualquer'])->id,
        ];
    }
}

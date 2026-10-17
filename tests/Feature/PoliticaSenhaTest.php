<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PoliticaSenhaTest extends TestCase
{
    use RefreshDatabase;

    public static function senhasFracas(): array
    {
        return [
            'só números' => ['12345678', 'letra'],
            'só letras' => ['abcdefgh', 'número'],
            'curta' => ['abc1234', '8 caracteres'],
        ];
    }

    #[DataProvider('senhasFracas')]
    public function test_cadastro_pelo_admin_recusa_senha_fraca(string $senha, string $trecho): void
    {
        $this->actingAs($this->usuarioCom('user-create'))
            ->postJson(route('user.store'), $this->dadosUsuario(['senha' => $senha]))
            ->assertStatus(400)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn (string $mensagem) => str_contains($mensagem, $trecho));

        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
    }

    public function test_cadastro_pelo_admin_aceita_senha_valida(): void
    {
        $this->actingAs($this->usuarioCom('user-create'))
            ->postJson(route('user.store'), $this->dadosUsuario(['senha' => 'abc12345']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('abc12345', User::where('email', 'novo@example.com')->value('password')));
    }

    public function test_edicao_sem_senha_mantem_a_atual(): void
    {
        $alvo = User::factory()->create(['password' => Hash::make('original1')]);

        $this->actingAs($this->usuarioCom('user-edit'))
            ->postJson(route('user.store', $alvo->id), $this->dadosUsuario([
                'nome' => 'Nome novo', 'email' => $alvo->email, 'senha' => '',
            ]))
            ->assertOk();

        $alvo->refresh();
        $this->assertSame('Nome novo', $alvo->name);
        $this->assertTrue(Hash::check('original1', $alvo->password));
    }

    public function test_edicao_com_senha_fraca_e_recusada(): void
    {
        $alvo = User::factory()->create();

        $this->actingAs($this->usuarioCom('user-edit'))
            ->postJson(route('user.store', $alvo->id), $this->dadosUsuario(['email' => $alvo->email, 'senha' => '12345678']))
            ->assertStatus(400);
    }

    #[DataProvider('senhasFracas')]
    public function test_troca_no_perfil_recusa_senha_fraca(string $senha, string $trecho): void
    {
        $user = User::factory()->create(['password' => Hash::make('atual123')]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'old_password' => 'atual123', 'password' => $senha, 'password_confirmation' => $senha,
            ])
            ->assertSessionHasErrors('password');

        $this->assertStringContainsString($trecho, session('errors')->first('password'));
        $this->assertTrue(Hash::check('atual123', $user->fresh()->password));
    }

    public function test_troca_no_perfil_aceita_senha_valida(): void
    {
        $user = User::factory()->create(['password' => Hash::make('atual123')]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'old_password' => 'atual123', 'password' => 'abc12345', 'password_confirmation' => 'abc12345',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('abc12345', $user->fresh()->password));
    }

    #[DataProvider('senhasFracas')]
    public function test_redefinicao_recusa_senha_fraca(string $senha, string $trecho): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->from(route('password.reset', $token))
            ->post(route('password.update'), [
                'token' => $token, 'email' => $user->email, 'password' => $senha, 'password_confirmation' => $senha,
            ])
            ->assertSessionHasErrors('password');

        $this->assertStringContainsString($trecho, session('errors')->first('password'));
    }

    public function test_redefinicao_aceita_senha_valida(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'abc12345', 'password_confirmation' => 'abc12345',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('abc12345', $user->fresh()->password));
    }

    private function dadosUsuario(array $dados = []): array
    {
        return $dados + [
            'nome' => 'Novo usuário',
            'email' => 'novo@example.com',
            'grupo_acesso' => Role::create(['name' => 'qualquer', 'display_name' => 'Qualquer'])->id,
        ];
    }
}

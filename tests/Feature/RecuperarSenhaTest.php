<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RedefinirSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecuperarSenhaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_tela_de_pedido_abre(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Esqueci minha senha');
    }

    public function test_pedir_o_link_envia_redefinir_senha(): void
    {
        $user = User::factory()->create();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertSentTo($user, RedefinirSenha::class);
    }

    public function test_email_em_portugues_com_validade(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, RedefinirSenha::class, function (RedefinirSenha $notificacao) use ($user) {
            $mail = $notificacao->toMail($user);

            return $mail->subject === 'Redefinição de senha — '.config('app.name')
                && $mail->actionText === 'Redefinir senha'
                && in_array('Este link vale por 60 minutos.', $mail->outroLines, true);
        });
    }

    public function test_email_inexistente_nao_revela_se_a_conta_existe(): void
    {
        $existente = User::factory()->create();

        $comConta = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $existente->email]);
        $semConta = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ninguem@example.com']);

        $semConta->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
        $this->assertSame(
            $comConta->getSession()->get('status'),
            $semConta->getSession()->get('status'),
        );
        Notification::assertSentTimes(RedefinirSenha::class, 1);
    }

    public function test_pedido_repetido_nao_revela_a_conta(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_token_redefine_a_senha(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, RedefinirSenha::class, function (RedefinirSenha $notificacao) use (&$token) {
            $token = $notificacao->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Redefinir senha');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(Hash::check('nova-senha-123', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }
}

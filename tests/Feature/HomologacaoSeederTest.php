<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\HomologacaoSeeder;
use Database\Seeders\PerfilSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserCreateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Tests\TestCase;

/** Seeders seguros fora do ambiente local (D9.2 e D9.3 da fatia 9). */
class HomologacaoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, PerfilSeeder::class]);
    }

    public function test_administrador_fora_do_local_exige_admin_email(): void
    {
        $this->app['env'] = 'homologacao';
        config(['implantacao.admin_email' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Defina ADMIN_EMAIL');

        (new UserCreateSeeder())->run();
    }

    public function test_administrador_fora_do_local_tem_senha_aleatoria(): void
    {
        $this->app['env'] = 'homologacao';
        config(['implantacao.admin_email' => 'ti@ablprime.com.br']);

        (new UserCreateSeeder())->run();

        $admin = User::where('email', 'ti@ablprime.com.br')->sole();
        $this->assertFalse(Hash::check('mudar@123', $admin->password));
        $this->assertSame(0, User::where('email', 'ronaldo@rmsti.com.br')->count());
        $this->assertTrue($admin->can('user-list'));
    }

    public function test_um_usuario_por_perfil_menos_administrador(): void
    {
        $this->app['env'] = 'homologacao';
        config([
            'implantacao.admin_email' => 'ti@ablprime.com.br',
            'implantacao.homologacao_usuarios' => 'rh:Maria@ABLPrime.com.br, financeiro:joao@ablprime.com.br,gestor:invalido',
        ]);

        $this->artisan('db:seed', ['--class' => HomologacaoSeeder::class])
            ->expectsOutputToContain('maria@ablprime.com.br')
            ->assertSuccessful();

        $emails = User::with('roles')->get()->mapWithKeys(fn (User $u) => [$u->roles->first()->name => $u->email])->all();
        $this->assertEqualsCanonicalizing(['rh', 'gestor', 'financeiro', 'colaborador'], array_keys($emails));
        $this->assertSame('maria@ablprime.com.br', $emails['rh']);
        $this->assertSame('joao@ablprime.com.br', $emails['financeiro']);
        $this->assertSame('homologacao+gestor@ablprime.com.br', $emails['gestor']);
        $this->assertSame('homologacao+colaborador@ablprime.com.br', $emails['colaborador']);
        $this->assertTrue(User::where('email', 'maria@ablprime.com.br')->sole()->can('pessoa-importar'));
    }

    public function test_rodar_de_novo_nao_troca_a_senha(): void
    {
        $this->app['env'] = 'homologacao';
        config(['implantacao.homologacao_usuarios' => 'rh:maria@ablprime.com.br']);

        $this->seed(HomologacaoSeeder::class);
        $hash = User::where('email', 'maria@ablprime.com.br')->value('password');

        $this->artisan('db:seed', ['--class' => HomologacaoSeeder::class])
            ->expectsOutputToContain('já existia; senha mantida')
            ->assertSuccessful();

        $this->assertSame($hash, User::where('email', 'maria@ablprime.com.br')->value('password'));
        $this->assertSame(4, User::count());
    }

    public function test_recusa_producao(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        (new HomologacaoSeeder())->run();
    }

    public function test_senhas_geradas_passam_na_politica(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $senha = Str::password(12, symbols: false);
            $this->assertTrue(Validator::make(['senha' => $senha], ['senha' => Password::defaults()])->passes(), $senha);
        }
    }
}

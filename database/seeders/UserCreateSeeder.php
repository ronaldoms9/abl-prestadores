<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Administrador inicial. No ambiente local (e nos testes), e-mail e senha fixos
 * para o desenvolvimento. Em qualquer servidor, e-mail de ADMIN_EMAIL e senha
 * aleatória mostrada uma única vez (D9.2 da fatia 9).
 */
class UserCreateSeeder extends Seeder
{
    public function run(): void
    {
        [$email, $senha] = app()->environment('local', 'testing')
            ? ['ronaldo@rmsti.com.br', 'mudar@123']
            : [$this->emailDoServidor(), Str::password(12, symbols: false)];

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'email_verified_at' => now(),
                'password' => Hash::make($senha),
            ],
        );

        if ($user->wasRecentlyCreated && ! app()->environment('local', 'testing')) {
            $this->command?->warn("Administrador criado: {$email}");
            $this->command?->warn("Senha (aparece só agora; troque no primeiro acesso): {$senha}");
        }

        // Único ponto em que o código usa o nome de um perfil, e só no seed.
        $administrador = Role::where('name', PerfilSeeder::ADMINISTRADOR)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$administrador->id]);
    }

    private function emailDoServidor(): string
    {
        $email = config('implantacao.admin_email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Defina ADMIN_EMAIL no .env antes de rodar o seed fora do ambiente local.');
        }

        return $email;
    }
}

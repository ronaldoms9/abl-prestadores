<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Um usuário ativo por perfil (menos Administrador) para a ABL PRIME validar a
 * homologação (D9.3 da fatia 9). Fora do DatabaseSeeder:
 *
 *     php artisan db:seed --class=HomologacaoSeeder --force
 *
 * E-mails de HOMOLOGACAO_USUARIOS; perfil sem e-mail usa
 * homologacao+<perfil>@<domínio de ADMIN_EMAIL>. Senhas aleatórias, mostradas uma
 * vez. Usuário que já existe não é alterado.
 */
class HomologacaoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('HomologacaoSeeder não roda em produção.');
        }

        $emails = $this->emailsInformados();
        $criados = [];

        Role::where('name', '!=', PerfilSeeder::ADMINISTRADOR)->orderBy('id')->get()
            ->each(function (Role $perfil) use ($emails, &$criados) {
                $email = $emails[$perfil->name] ?? $this->emailPadrao($perfil->name);
                $senha = Str::password(12, symbols: false);

                $user = User::firstOrCreate(['email' => $email], [
                    'name' => "Homologação {$perfil->display_name}",
                    'email_verified_at' => now(),
                    'password' => Hash::make($senha),
                    'ativo' => true,
                ]);
                $user->roles()->syncWithoutDetaching([$perfil->id]);

                $criados[] = [$perfil->display_name, $email, $user->wasRecentlyCreated ? $senha : '(já existia; senha mantida)'];
            });

        $this->command?->table(['Perfil', 'E-mail', 'Senha (aparece só agora)'], $criados);
    }

    /** @return array<string, string> nome do perfil => e-mail */
    private function emailsInformados(): array
    {
        return collect(explode(',', (string) config('implantacao.homologacao_usuarios')))
            ->map(fn (string $par) => array_map('trim', explode(':', $par, 2)))
            ->filter(fn (array $par) => count($par) === 2 && filter_var($par[1], FILTER_VALIDATE_EMAIL))
            ->mapWithKeys(fn (array $par) => [Str::lower($par[0]) => Str::lower($par[1])])
            ->all();
    }

    private function emailPadrao(string $perfil): string
    {
        $admin = (string) config('implantacao.admin_email');
        $dominio = str_contains($admin, '@') ? Str::after($admin, '@') : 'example.com';

        return "homologacao+{$perfil}@{$dominio}";
    }
}

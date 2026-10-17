<?php

namespace Tests;

use App\Models\User;
use App\Permission;
use App\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Usuário ligado a um perfil de nome aleatório com as permissões dadas.
     * O nome aleatório prova que nenhuma checagem depende do nome do perfil.
     */
    protected function usuarioCom(string ...$permissoes): User
    {
        $perfil = Role::create(['name' => 'perfil-'.Str::random(10), 'display_name' => 'Perfil de teste']);

        foreach ($permissoes as $nome) {
            $perfil->permissions()->attach(
                Permission::firstOrCreate(['name' => $nome], ['display_name' => $nome])->id
            );
        }

        $user = User::factory()->create();
        $user->roles()->attach($perfil);

        return $user;
    }
}

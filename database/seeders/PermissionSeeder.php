<?php

namespace Database\Seeders;

use App\Permission;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $permissoes = [
            ['name' => 'user-list', 'display_name' => 'Listar Usuários', 'description' => 'Usuário acessa tela de controle de usuário e lista os usuarios ativos no sistema', 'module'=> 'Usuários'],
            ['name' => 'user-create', 'display_name' => 'Cadastrar Usuários', 'description' => 'Dá acesso à cadastrar usuários', 'module' => 'Usuários'],
            ['name' => 'user-edit', 'display_name' => 'Editar Usuários', 'description' => 'Dá acesso à editar usuários', 'module' => 'Usuários'],
            ['name' => 'user-delete', 'display_name' => 'Excluir Usuários', 'description'=> 'Dá acesso a remover usuários do sistema', 'module' => 'Usuários'],

            ['name' => 'grupo-list', 'display_name' => 'Listar Grupos de Acesso', 'description' => 'Usuário acessa tela de controle de grupos de acesso e lista os grupos de acesso ativos no sistema', 'module'=> 'Grupos De Acesso'],
            ['name' => 'grupo-create', 'display_name' => 'Cadastrar Grupos de Acesso', 'description' => 'Dá acesso à cadastrar grupos de acesso', 'module' => 'Grupos De Acesso'],
            ['name' => 'grupo-edit', 'display_name' => 'Editar Grupos de Acesso', 'description' => 'Dá acesso à editar grupos de acesso', 'module' => 'Grupos De Acesso'],
            ['name' => 'grupo-delete', 'display_name' => 'Excluir Grupos de Acesso', 'description'=> 'Dá acesso a remover grupos de acesso do sistema', 'module' => 'Grupos De Acesso'],
        ];

        $role = Role::where('name', 'superadmin')->first();

        if(!$role){
            $role = Role::create([
               'name' => 'superadmin',
               'display_name' => 'Super Administrador',
               'description' => 'Tem permissão máxima no sistema'
            ]);
        }

        foreach($permissoes as $permissao){
            $perm = Permission::where('name', $permissao['name'])->first();
            if(!$perm)
                $perm = Permission::create($permissao);


            $attach = ['permission_id' => $perm->id, 'role_id' => $role->id];
            DB::table('permission_role')->where($attach)->delete();

            DB::table('permission_role')
                ->insert($attach);
        }

    }
}

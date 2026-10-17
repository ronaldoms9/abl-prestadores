<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserCreateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'ronaldo@rmsti.com.br'],
            [
                'name' => 'Administrador',
                'email_verified_at' => now(),
                'password' => Hash::make('mudar@123'),
            ],
        );

        // Único ponto em que o código usa o nome de um perfil, e só no seed.
        $administrador = Role::where('name', PerfilSeeder::ADMINISTRADOR)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$administrador->id]);
    }
}

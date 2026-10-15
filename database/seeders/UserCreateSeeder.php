<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserCreateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //

        $datas = [[
            'tenant_id' => null, // time interno
            'name' => 'Administrador',
            'email' => 'ronaldo@rmsti.com.br',
            'email_verified_at' => now(),
            'password' => Hash::make('mudar@123'), // password
            'remember_token' => Str::random(10),
        ]];

        foreach($datas as $data){
            $u = User::create($data);

            $u->roles()->attach(1);
        }
//        User::insert();

    }
}

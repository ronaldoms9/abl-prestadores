<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            [
                'nome' => 'Negócio de Demonstração',
                'ativo_ate' => today()->addMonths(3),
                'waha_instance' => 'waha-1',
                'waha_session' => 'demo',
            ]
        );

        User::firstOrCreate(
            ['email' => 'dono@demo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Dono da Demonstração',
                'email_verified_at' => now(),
                'password' => Hash::make('mudar@123'),
            ]
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Permission;
use App\Role;
use Database\Seeders\PerfilSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_rodar_duas_vezes_nao_duplica_nada(): void
    {
        $this->seed();
        $contagem = $this->contagem();

        $this->seed();

        $this->assertSame($contagem, $this->contagem());
        $this->assertSame(17, $contagem['permissoes']);
        $this->assertSame(5, $contagem['perfis']);
        $this->assertSame(1, $contagem['usuarios']);
    }

    public function test_administrador_recebe_todas_as_permissoes_e_o_usuario_inicial(): void
    {
        $this->seed();

        $admin = Role::where('name', 'administrador')->first();
        $this->assertSame(Permission::count(), $admin->permissions()->count());
        $this->assertTrue(User::first()->can('horizon-acessar'));
    }

    public function test_permissao_retirada_pelo_admin_nao_volta(): void
    {
        $this->seed();
        $rh = Role::where('name', 'rh')->first();
        $rh->permissions()->detach(Permission::where('name', 'pessoa-importar')->value('id'));

        $this->seed();

        $this->assertFalse($rh->permissions()->where('name', 'pessoa-importar')->exists());
        $this->assertTrue($rh->permissions()->where('name', 'pessoa-list')->exists());
    }

    public function test_permissao_nova_chega_ao_admin_e_aos_perfis_que_a_tem_como_padrao(): void
    {
        $this->seed();

        $this->seederDePermissoesCom(['name' => 'pessoa-exportar', 'display_name' => 'Exportar pessoas', 'module' => 'Pessoas'])->run();
        $this->seederDePerfisCom('rh', 'pessoa-exportar')->run();

        $this->assertSame(['pessoa-exportar'], PermissionSeeder::$criadas);
        $this->assertTrue(Role::where('name', 'administrador')->first()->permissions()->where('name', 'pessoa-exportar')->exists());
        $this->assertTrue(Role::where('name', 'rh')->first()->permissions()->where('name', 'pessoa-exportar')->exists());
        $this->assertFalse(Role::where('name', 'gestor')->first()->permissions()->where('name', 'pessoa-exportar')->exists());
    }

    public function test_superadmin_existente_vira_administrador_mantendo_id_e_usuarios(): void
    {
        $superadmin = Role::create(['name' => 'superadmin', 'display_name' => 'Super Administrador']);
        $user = User::factory()->create();
        $user->roles()->attach($superadmin);

        $this->seed();

        $admin = Role::where('name', 'administrador')->first();
        $this->assertSame($superadmin->id, $admin->id);
        $this->assertSame('Administrador', $admin->display_name);
        $this->assertTrue($admin->users()->whereKey($user->id)->exists());
        $this->assertFalse(Role::where('name', 'superadmin')->exists());
    }

    /** @return array<string, int> */
    private function contagem(): array
    {
        return [
            'permissoes' => Permission::count(),
            'perfis' => Role::count(),
            'usuarios' => User::count(),
            'permission_role' => DB::table('permission_role')->count(),
            'role_user' => DB::table('role_user')->count(),
        ];
    }

    private function seederDePermissoesCom(array $nova): PermissionSeeder
    {
        return new class($nova) extends PermissionSeeder
        {
            public function __construct(private array $nova)
            {
            }

            protected function permissoes(): array
            {
                return [...parent::permissoes(), $this->nova];
            }
        };
    }

    private function seederDePerfisCom(string $perfil, string $permissao): PerfilSeeder
    {
        return new class($perfil, $permissao) extends PerfilSeeder
        {
            public function __construct(private string $perfil, private string $permissao)
            {
            }

            protected function perfis(): array
            {
                $perfis = parent::perfis();
                $perfis[$this->perfil]['permissoes'][] = $this->permissao;

                return $perfis;
            }
        };
    }
}

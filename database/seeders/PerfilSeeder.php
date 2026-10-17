<?php

namespace Database\Seeders;

use App\Permission;
use App\Role;
use Illuminate\Database\Seeder;

/**
 * Perfis iniciais e suas permissões padrão, sem desfazer o que o admin mudou
 * na tela:
 * - perfil criado agora recebe todas as permissões padrão;
 * - perfil que já existia recebe só as padrão criadas nesta execução do
 *   PermissionSeeder (uma permissão retirada pelo admin não volta);
 * - o administrador recebe todas as permissões, sempre.
 */
class PerfilSeeder extends Seeder
{
    public const ADMINISTRADOR = 'administrador';

    public function run(): void
    {
        $this->renomearSuperadmin();

        $idsPorNome = Permission::pluck('id', 'name');
        $criadasAgora = PermissionSeeder::$criadas;

        foreach ($this->perfis() as $nome => $perfil) {
            $role = Role::firstOrCreate(
                ['name' => $nome],
                ['display_name' => $perfil['display_name'], 'description' => $perfil['description']],
            );

            if ($nome === self::ADMINISTRADOR) {
                $role->permissions()->syncWithoutDetaching($idsPorNome->values());

                continue;
            }

            $padrao = $role->wasRecentlyCreated
                ? $perfil['permissoes']
                : array_intersect($perfil['permissoes'], $criadasAgora);

            $role->permissions()->syncWithoutDetaching($idsPorNome->only($padrao)->values());
        }
    }

    /**
     * Perfil => permissões padrão. O administrador recebe todas.
     *
     * @return array<string, array{display_name: string, description: string, permissoes: array<int, string>}>
     */
    protected function perfis(): array
    {
        return [
            self::ADMINISTRADOR => [
                'display_name' => 'Administrador',
                'description' => 'Acesso a todas as funções do sistema',
                'permissoes' => [],
            ],
            'rh' => [
                'display_name' => 'RH',
                'description' => 'Cadastro de pessoas e carga de planilha',
                'permissoes' => ['pessoa-list', 'pessoa-importar', 'empresa-list', 'obra-list'],
            ],
            'gestor' => [
                'display_name' => 'Gestor',
                'description' => 'Consulta de pessoas, empresas e obras',
                'permissoes' => ['pessoa-list', 'empresa-list', 'obra-list'],
            ],
            'financeiro' => [
                'display_name' => 'Financeiro',
                'description' => 'Consulta de cadastros e acompanhamento das integrações',
                'permissoes' => ['pessoa-list', 'empresa-list', 'obra-list', 'integracao-list', 'integracao-sincronizar', 'integracao-reenviar'],
            ],
            'colaborador' => [
                'display_name' => 'Colaborador',
                'description' => 'Sem acesso administrativo (portal na E5)',
                'permissoes' => [],
            ],
        ];
    }

    /** O antigo "superadmin" vira "administrador", mantendo id e usuários. */
    private function renomearSuperadmin(): void
    {
        if (Role::where('name', self::ADMINISTRADOR)->exists()) {
            return;
        }

        Role::where('name', 'superadmin')->update([
            'name' => self::ADMINISTRADOR,
            'display_name' => 'Administrador',
        ]);
    }
}

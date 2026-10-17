<?php

namespace Database\Seeders;

use App\Permission;
use Illuminate\Database\Seeder;

/**
 * Cria e atualiza as permissões. Não mexe nos vínculos com perfis: isso é
 * do PerfilSeeder, que usa a lista das permissões criadas nesta execução.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<int, string> Nomes das permissões criadas na última execução. */
    public static array $criadas = [];

    public function run(): void
    {
        static::$criadas = [];

        foreach ($this->permissoes() as $permissao) {
            $registro = Permission::updateOrCreate(
                ['name' => $permissao['name']],
                [
                    'display_name' => $permissao['display_name'],
                    'description' => $permissao['description'] ?? null,
                    'module' => $permissao['module'],
                ],
            );

            if ($registro->wasRecentlyCreated) {
                static::$criadas[] = $registro->name;
            }
        }
    }

    /**
     * @return array<int, array{name: string, display_name: string, description?: string, module: string}>
     */
    protected function permissoes(): array
    {
        return [
            ['name' => 'user-list', 'display_name' => 'Listar usuários', 'description' => 'Acessa a tela de usuários e lista os cadastrados', 'module' => 'Usuários'],
            ['name' => 'user-create', 'display_name' => 'Cadastrar usuários', 'description' => 'Cadastra usuários', 'module' => 'Usuários'],
            ['name' => 'user-edit', 'display_name' => 'Editar usuários', 'description' => 'Edita usuários, inclusive ativa e desativa', 'module' => 'Usuários'],
            ['name' => 'user-delete', 'display_name' => 'Excluir usuários', 'description' => 'Exclui usuários cadastrados por engano', 'module' => 'Usuários'],

            ['name' => 'grupo-list', 'display_name' => 'Listar perfis', 'description' => 'Acessa a tela de perfis e lista os cadastrados', 'module' => 'Perfis'],
            ['name' => 'grupo-create', 'display_name' => 'Cadastrar perfis', 'description' => 'Cadastra perfis de acesso', 'module' => 'Perfis'],
            ['name' => 'grupo-edit', 'display_name' => 'Editar perfis', 'description' => 'Edita perfis e suas permissões', 'module' => 'Perfis'],
            ['name' => 'grupo-delete', 'display_name' => 'Excluir perfis', 'description' => 'Exclui perfis de acesso', 'module' => 'Perfis'],

            ['name' => 'empresa-list', 'display_name' => 'Ver empresas', 'module' => 'Cadastros UAU'],
            ['name' => 'obra-list', 'display_name' => 'Ver obras', 'module' => 'Cadastros UAU'],

            ['name' => 'pessoa-list', 'display_name' => 'Ver pessoas', 'module' => 'Pessoas'],
            ['name' => 'pessoa-importar', 'display_name' => 'Carregar planilha de pessoas', 'description' => 'Carrega a planilha e vê os relatórios de carga', 'module' => 'Pessoas'],

            ['name' => 'integracao-list', 'display_name' => 'Ver monitoramento das integrações', 'module' => 'Integrações'],
            ['name' => 'integracao-sincronizar', 'display_name' => 'Sincronizar agora', 'module' => 'Integrações'],
            ['name' => 'integracao-reenviar', 'display_name' => 'Reprocessar falha de integração', 'module' => 'Integrações'],

            ['name' => 'auditoria-list', 'display_name' => 'Ver auditoria', 'module' => 'Configurações'],
            ['name' => 'horizon-acessar', 'display_name' => 'Acessar painel de filas (Horizon)', 'module' => 'Configurações'],
        ];
    }
}

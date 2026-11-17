<?php

namespace Database\Seeders;

use App\Enums\TipoAprovadorFerias;
use App\Models\FeriasNivel;
use App\Permission;
use Illuminate\Database\Seeder;

/**
 * Fluxo padrão de aprovação de férias (spec 3.4): 1. gestor da equipe; 2. quem
 * tem ferias-aprovar-rh. Só cria quando ainda não há nenhum nível, para não
 * desfazer o que o admin configurou. Roda depois do PermissionSeeder.
 */
class FeriasNivelSeeder extends Seeder
{
    public function run(): void
    {
        if (FeriasNivel::withTrashed()->exists()) {
            return;
        }

        FeriasNivel::create(['ordem' => 1, 'tipo_aprovador' => TipoAprovadorFerias::GestorEquipe, 'ativo' => true]);
        FeriasNivel::create([
            'ordem' => 2,
            'tipo_aprovador' => TipoAprovadorFerias::Permissao,
            'permission_id' => Permission::where('name', 'ferias-aprovar-rh')->value('id'),
            'ativo' => true,
        ]);
    }
}

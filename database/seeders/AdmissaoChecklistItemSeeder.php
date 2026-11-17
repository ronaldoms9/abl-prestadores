<?php

namespace Database\Seeders;

use App\Models\AdmissaoChecklistItem;
use App\Support\Admissao\ItensAutomaticos;
use Illuminate\Database\Seeder;

/**
 * Itens automáticos padrão do checklist de admissão. Só cria os que faltam: o
 * que o admin mudou (nome, obrigatoriedade, ordem, ativo) não volta ao padrão.
 * Os itens manuais são da ABL PRIME e se cadastram na tela.
 */
class AdmissaoChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ItensAutomaticos::PADRAO as $chave => [$nome, $ordem]) {
            AdmissaoChecklistItem::withTrashed()->firstOrCreate(['chave' => $chave], ['nome' => $nome, 'ordem' => $ordem, 'obrigatorio' => true, 'ativo' => true]);
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sem RefreshDatabase de propósito: o banco em memória começa vazio, e o
 * teste controla o ciclo inteiro para que todo down() seja executado.
 */
class MigrationsTest extends TestCase
{
    public function test_migrate_reset_e_migrate_de_novo_funcionam(): void
    {
        $this->artisanSemFalha('migrate');
        $this->assertTrue(Schema::hasColumn('users', 'pessoa_fisica_id'));
        $this->assertTrue(Schema::hasTable('vinculos_sugeridos'));
        $this->assertTrue(Schema::hasTable('pessoa_juridica_responsaveis'));
        foreach (['cargos', 'colaboradores', 'equipes', 'equipe_gestores', 'colaborador_equipes', 'dados_bancarios', 'documentos', 'reajustes', 'remuneracoes', 'qualificacao_tipos', 'cargo_qualificacoes', 'colaborador_qualificacoes', 'configuracoes', 'contrato_modelos', 'contratos', 'beneficios', 'beneficio_valores', 'colaborador_beneficios', 'cargo_beneficios', 'admissao_checklist_itens', 'admissao_checklist'] as $tabela) {
            $this->assertTrue(Schema::hasTable($tabela), $tabela);
        }

        $this->artisanSemFalha('migrate:reset');
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('empresas'));

        $this->artisanSemFalha('migrate');
        $this->assertTrue(Schema::hasColumn('users', 'pessoa_fisica_id'));
    }

    private function artisanSemFalha(string $comando): void
    {
        $codigo = Artisan::call($comando);
        $saida = Artisan::output();

        $this->assertSame(0, $codigo, $saida);
        $this->assertStringNotContainsString('FAIL', $saida, $saida);
    }
}

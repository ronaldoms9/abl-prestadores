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

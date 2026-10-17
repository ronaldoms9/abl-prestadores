<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    public function test_migrate_reset_seguido_de_migrate_funciona(): void
    {
        $resetCode = Artisan::call('migrate:reset');
        $this->assertSame(0, $resetCode, Artisan::output());

        $migrateCode = Artisan::call('migrate');
        $this->assertSame(0, $migrateCode, Artisan::output());
    }
}

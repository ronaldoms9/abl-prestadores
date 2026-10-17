<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pessoa_fisica_id')
                ->nullable()
                ->unique()
                ->after('ativo')
                ->constrained('pessoas_fisicas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A ordem importa no SQLite: FK, depois o índice único, depois a coluna.
            $table->dropForeign(['pessoa_fisica_id']);
            $table->dropUnique(['pessoa_fisica_id']);
            $table->dropColumn('pessoa_fisica_id');
        });
    }
};

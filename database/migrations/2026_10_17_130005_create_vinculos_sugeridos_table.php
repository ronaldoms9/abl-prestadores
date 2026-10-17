<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculos_sugeridos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_fisica_id')->constrained('pessoas_fisicas')->cascadeOnDelete();
            $table->foreignId('pessoa_juridica_id')->constrained('pessoas_juridicas')->cascadeOnDelete();
            $table->foreignId('importacao_pessoa_id')->nullable()->constrained('importacoes_pessoas')->nullOnDelete();
            $table->timestamp('utilizado_em')->nullable();
            $table->auditoria();

            $table->unique(['pessoa_fisica_id', 'pessoa_juridica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculos_sugeridos');
    }
};

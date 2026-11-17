<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tipos de documento configuráveis e as colunas do arquivo anexado (E2, fatia 5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            // pessoa_fisica, pessoa_juridica, colaborador ou todos
            $table->string('aplica_a', 20)->default('todos');
            $table->boolean('exige_validade')->default(false);
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('documento_tipo_id')->nullable()->after('documentavel_id')->constrained('documento_tipos')->restrictOnDelete();
            $table->string('arquivo_nome')->nullable()->after('arquivo_path');
            $table->string('arquivo_mime', 100)->nullable()->after('arquivo_nome');
            $table->unsignedInteger('arquivo_tamanho')->nullable()->after('arquivo_mime');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_tipo_id');
            $table->dropColumn(['arquivo_nome', 'arquivo_mime', 'arquivo_tamanho']);
        });
        Schema::dropIfExists('documento_tipos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pessoa física ou jurídica (morph com os apelidos do AppServiceProvider).
        Schema::create('dados_bancarios', function (Blueprint $table) {
            $table->id();
            $table->morphs('pessoa');
            $table->string('banco', 80);
            $table->string('agencia', 20);
            $table->string('conta', 30);
            $table->string('tipo', 20)->default('corrente');
            $table->string('tipo_chave_pix', 20)->nullable();
            $table->string('chave_pix')->nullable();
            $table->boolean('principal')->default(false);
            $table->auditoria();
        });

        // Documento de pessoa física, jurídica ou colaborador; a validade alimenta os alertas.
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentavel');
            $table->string('tipo', 60);
            $table->string('arquivo_path')->nullable();
            $table->date('validade')->nullable();
            $table->text('observacao')->nullable();
            $table->auditoria();
            $table->index('validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('dados_bancarios');
    }
};

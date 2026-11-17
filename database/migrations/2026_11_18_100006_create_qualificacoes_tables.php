<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualificacao_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('categoria', 30);
            $table->unsignedSmallInteger('validade_meses')->nullable();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });

        // Matriz de exigências por cargo.
        Schema::create('cargo_qualificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->foreignId('qualificacao_tipo_id')->constrained('qualificacao_tipos')->cascadeOnDelete();
            $table->boolean('obrigatoria')->default(true);
            $table->auditoria();
            $table->unique(['cargo_id', 'qualificacao_tipo_id']);
        });

        Schema::create('colaborador_qualificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('qualificacao_tipo_id')->constrained('qualificacao_tipos')->restrictOnDelete();
            $table->date('data_conclusao');
            $table->date('data_validade')->nullable();
            $table->string('certificado_path')->nullable();
            $table->string('instituicao')->nullable();
            $table->auditoria();
            // Nome explícito: o gerado passa de 64 caracteres, o limite do MySQL.
            $table->index(['colaborador_id', 'qualificacao_tipo_id'], 'colab_qualif_colab_tipo_index');
            $table->index('data_validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaborador_qualificacoes');
        Schema::dropIfExists('cargo_qualificacoes');
        Schema::dropIfExists('qualificacao_tipos');
    }
};

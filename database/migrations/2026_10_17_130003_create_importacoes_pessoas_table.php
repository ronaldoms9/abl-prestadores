<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes_pessoas', function (Blueprint $table) {
            $table->id();
            $table->string('arquivo_path');
            $table->string('nome_original');
            $table->string('status', 12)->default('pendente');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->json('resumo')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->auditoria();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_pessoas');
    }
};

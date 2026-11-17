<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos versionados e contratos gerados. O `motivo` do modelo (só aditivo) diz
 * para que ele serve: prorrogação, reajuste, mudança de cargo... Sem motivo, é genérico. Salvar um modelo em uso cria uma
 * linha nova (versao + 1, modelo_anterior_id) e desativa a anterior; os contratos
 * continuam apontando para a versão com que foram gerados.
 *
 * Contrato gerado é imutável. Aditivo e distrato apontam para o contrato pai
 * (contrato_pai_id). `ano` e `sequencia` sustentam a numeração AAAA/NNNN sem
 * colisão: o número é único e é gravado dentro de uma transação com lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo', 20);
            $table->string('motivo', 20)->nullable();
            $table->longText('corpo_html');
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('modelo_anterior_id')->nullable()->constrained('contrato_modelos')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
            $table->index(['tipo', 'ativo']);
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('contrato_modelo_id')->constrained('contrato_modelos')->restrictOnDelete();
            $table->foreignId('contrato_pai_id')->nullable()->constrained('contratos')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('motivo', 20)->nullable();
            $table->string('numero', 30)->nullable()->unique();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->unsignedInteger('sequencia')->nullable();
            $table->longText('conteudo_renderizado')->nullable();
            $table->json('dados')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('assinado_path')->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->string('status', 20)->default('rascunho');
            $table->timestamp('gerado_em')->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'tipo', 'status']);
            $table->index(['tipo', 'status', 'vigencia_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('contrato_modelos');
    }
};

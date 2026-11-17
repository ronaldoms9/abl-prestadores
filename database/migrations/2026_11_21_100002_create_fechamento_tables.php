<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendário de trabalho e fechamento mensal (E4). A competência é o mês
 * (ano_mes, "2026-11") e cada colaborador tem um fechamento por competência,
 * com a memória de cálculo linha a linha em fechamento_itens (origem morph).
 * Fechamento aprovado é imutável e guarda o snapshot dos valores usados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('data');
            $table->string('descricao');
            $table->string('abrangencia', 20);
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->auditoria();
            $table->index(['data', 'abrangencia']);
            $table->index('obra_id');
        });

        Schema::create('ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->date('inicio');
            $table->date('fim');
            $table->boolean('abona')->default(false);
            $table->string('observacao')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'inicio']);
        });

        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('ano_mes', 7)->unique();
            $table->string('status', 20)->default('aberta');
            $table->foreignId('aprovada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovada_em')->nullable();
            $table->auditoria();
        });

        Schema::create('fechamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->unsignedSmallInteger('dias_periodo')->default(0);
            $table->decimal('dias_trabalhados', 6, 2)->default(0);
            $table->decimal('valor_remuneracao', 12, 2)->default(0);
            $table->decimal('total_beneficios', 12, 2)->default(0);
            $table->decimal('total_ajustes', 12, 2)->default(0);
            $table->decimal('total_liquido', 12, 2)->default(0);
            $table->string('status', 20)->default('calculado');
            $table->json('snapshot')->nullable();
            $table->auditoria();
            $table->unique(['competencia_id', 'colaborador_id']);
        });

        Schema::create('fechamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fechamento_id')->constrained('fechamentos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('descricao');
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_unitario', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->nullableMorphs('origem');
            $table->auditoria();
            $table->index(['fechamento_id', 'tipo']);
        });

        Schema::create('ajustes_avulsos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->decimal('valor', 12, 2);
            $table->text('justificativa');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->auditoria();
            $table->index(['competencia_id', 'colaborador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_avulsos');
        Schema::dropIfExists('fechamento_itens');
        Schema::dropIfExists('fechamentos');
        Schema::dropIfExists('competencias');
        Schema::dropIfExists('ocorrencias');
        Schema::dropIfExists('feriados');
    }
};

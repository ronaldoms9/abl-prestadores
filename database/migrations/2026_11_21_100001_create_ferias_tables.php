<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Férias (E4). O período aquisitivo guarda direito e dias utilizados; saldo é
 * a diferença e nunca fica negativo. A solicitação passa por níveis de
 * aprovação (ferias_niveis); cada decisão fica em ferias_aprovacoes, com a
 * marca de escalada quando um nível foi pulado ou subiu por falta de aprovador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ferias_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->date('aquisitivo_inicio');
            $table->date('aquisitivo_fim');
            $table->unsignedSmallInteger('dias_direito')->default(30);
            $table->unsignedSmallInteger('dias_utilizados')->default(0);
            $table->date('limite_gozo');
            $table->auditoria();
            $table->unique(['colaborador_id', 'aquisitivo_inicio']);
            $table->index('limite_gozo');
        });

        Schema::create('ferias_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('ferias_periodo_id')->constrained('ferias_periodos')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->unsignedSmallInteger('dias');
            $table->string('status', 20)->default('pendente');
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('nivel_atual')->default(1);
            $table->string('observacao')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'status']);
            $table->index(['status', 'inicio']);
        });

        Schema::create('ferias_niveis', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('ordem');
            $table->string('tipo_aprovador', 20);
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
            $table->index(['ativo', 'ordem']);
        });

        Schema::create('ferias_aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ferias_solicitacao_id')->constrained('ferias_solicitacoes')->cascadeOnDelete();
            $table->unsignedTinyInteger('nivel');
            $table->foreignId('aprovador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decisao', 20);
            $table->string('observacao')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->boolean('escalada')->default(false);
            $table->string('motivo_escalada')->nullable();
            $table->auditoria();
            $table->index(['ferias_solicitacao_id', 'nivel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ferias_aprovacoes');
        Schema::dropIfExists('ferias_niveis');
        Schema::dropIfExists('ferias_solicitacoes');
        Schema::dropIfExists('ferias_periodos');
    }
};

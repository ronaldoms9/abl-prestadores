<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remuneração nunca é editada: reajuste encerra a linha aberta (vigencia_fim
 * nulo) e cria outra. Duas linhas abertas para o mesmo colaborador são
 * impedidas na ação, já que o MySQL não tem índice parcial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reajustes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->json('criterio')->nullable();
            $table->string('forma', 20);
            $table->decimal('valor', 12, 2);
            $table->date('vigencia_inicio');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->auditoria();
        });

        Schema::create('remuneracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->decimal('valor_mensal', 12, 2);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->foreignId('reajuste_id')->nullable()->constrained('reajustes')->nullOnDelete();
            $table->auditoria();
            $table->index(['colaborador_id', 'vigencia_fim']);
            $table->index(['colaborador_id', 'vigencia_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remuneracoes');
        Schema::dropIfExists('reajustes');
    }
};

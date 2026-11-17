<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benefícios com vigência, como a remuneração (README D5): reajuste encerra a
 * linha aberta (vigencia_fim nulo) e cria outra, na ação, não no banco.
 * O vínculo do colaborador usa inicio/fim; sem valor_personalizado vale a
 * tabela vigente. cargo_beneficios guarda os benefícios padrão do cargo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficios', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('tipo_calculo', 20);
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });

        Schema::create('beneficio_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficio_id')->constrained('beneficios')->cascadeOnDelete();
            $table->decimal('valor', 12, 2);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->auditoria();
            $table->index(['beneficio_id', 'vigencia_fim']);
            $table->index(['beneficio_id', 'vigencia_inicio']);
        });

        Schema::create('colaborador_beneficios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('beneficio_id')->constrained('beneficios')->restrictOnDelete();
            $table->decimal('valor_personalizado', 12, 2)->nullable();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'beneficio_id', 'fim'], 'colab_beneficios_colab_benef_fim_idx');
        });

        Schema::create('cargo_beneficios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->foreignId('beneficio_id')->constrained('beneficios')->cascadeOnDelete();
            $table->auditoria();
            $table->unique(['cargo_id', 'beneficio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_beneficios');
        Schema::dropIfExists('colaborador_beneficios');
        Schema::dropIfExists('beneficio_valores');
        Schema::dropIfExists('beneficios');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a PF à PJ pela qual presta serviço. Uma PF pode ter histórico com mais
 * de uma PJ, mas um colaborador ativo (ou em pré-admissão) por vez: regra
 * garantida na ação, já que o MySQL não tem índice parcial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_fisica_id')->constrained('pessoas_fisicas')->restrictOnDelete();
            $table->foreignId('pessoa_juridica_id')->constrained('pessoas_juridicas')->restrictOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->string('status', 20)->default('pre_admissao');
            $table->date('data_admissao')->nullable();
            $table->date('data_desligamento')->nullable();
            $table->text('motivo_desligamento')->nullable();
            $table->auditoria();
            $table->index(['pessoa_fisica_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaboradores');
    }
};

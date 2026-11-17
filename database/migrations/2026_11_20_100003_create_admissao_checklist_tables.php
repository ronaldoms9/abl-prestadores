<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * admissao_checklist_itens: o que o admin configura (ordem, obrigatório).
 * Item com `chave` é automático: o sistema marca sozinho, pela regra da chave.
 * admissao_checklist: o estado por colaborador. Guarda `item` (nome) como
 * cópia, para que mudar ou apagar o item configurado não reescreva admissões
 * já feitas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissao_checklist_itens', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('chave', 40)->nullable()->unique();
            $table->boolean('obrigatorio')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });

        Schema::create('admissao_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('admissao_checklist_item_id')->nullable()->constrained('admissao_checklist_itens')->nullOnDelete();
            $table->string('item');
            $table->string('chave', 40)->nullable();
            $table->boolean('obrigatorio')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('concluido')->default(false);
            $table->foreignId('concluido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('concluido_em')->nullable();
            $table->string('observacao')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'concluido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissao_checklist');
        Schema::dropIfExists('admissao_checklist_itens');
    }
};

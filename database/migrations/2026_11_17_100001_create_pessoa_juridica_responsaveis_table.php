<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoa física responsável por uma pessoa jurídica. Fica só no banco local:
 * o UAU não recebe esse vínculo. Tem vigência (a troca de responsável encerra
 * o registro anterior em vez de sobrescrevê-lo); a regra de um único
 * responsável aberto (fim nulo) por PJ é garantida na ação DefinirResponsavel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_juridica_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_juridica_id')->constrained('pessoas_juridicas')->cascadeOnDelete();
            $table->foreignId('pessoa_fisica_id')->constrained('pessoas_fisicas')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->auditoria();
            $table->index(['pessoa_juridica_id', 'fim']);
            $table->index('pessoa_fisica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_juridica_responsaveis');
    }
};

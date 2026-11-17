<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Equipes independentes da obra, com gestores e colaboradores por vigência (README D7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipes', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });

        Schema::create('equipe_gestores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipe_id')->constrained('equipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->auditoria();
            $table->index(['equipe_id', 'fim']);
            $table->index(['user_id', 'fim']);
        });

        Schema::create('colaborador_equipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('equipe_id')->constrained('equipes')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'fim']);
            $table->index(['equipe_id', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaborador_equipes');
        Schema::dropIfExists('equipe_gestores');
        Schema::dropIfExists('equipes');
    }
};

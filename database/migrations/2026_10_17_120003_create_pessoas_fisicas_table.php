<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas_fisicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('uau_codigo')->nullable()->unique();
            $table->char('cpf', 11)->unique();
            $table->string('nome')->index();
            $table->date('nascimento')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->json('endereco')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('sincronizado_em')->nullable();
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas_fisicas');
    }
};

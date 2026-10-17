<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas_juridicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('uau_codigo')->nullable()->unique();
            $table->char('cnpj', 14)->unique();
            $table->string('razao_social')->index();
            $table->string('nome_fantasia')->nullable();
            $table->string('regime_tributario', 30)->nullable();
            $table->string('inscricao_municipal')->nullable();
            $table->string('inscricao_estadual')->nullable();
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
        Schema::dropIfExists('pessoas_juridicas');
    }
};

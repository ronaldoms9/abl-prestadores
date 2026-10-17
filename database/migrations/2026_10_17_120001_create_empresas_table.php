<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('uau_codigo')->unique();
            $table->char('cnpj', 14)->nullable()->index();
            $table->string('razao_social');
            $table->string('inscricao_estadual')->nullable();
            $table->string('inscricao_municipal')->nullable();
            $table->json('endereco')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('sincronizado_em')->nullable();
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};

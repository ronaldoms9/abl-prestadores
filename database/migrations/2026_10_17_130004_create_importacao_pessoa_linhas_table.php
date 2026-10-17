<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacao_pessoa_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacao_pessoa_id')->constrained('importacoes_pessoas')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('cpf', 20)->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->string('resultado_pf', 15)->nullable();
            $table->string('resultado_pj', 15)->nullable();
            $table->text('mensagem')->nullable();
            $table->timestamps();

            $table->unique(['importacao_pessoa_id', 'linha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacao_pessoa_linhas');
    }
};

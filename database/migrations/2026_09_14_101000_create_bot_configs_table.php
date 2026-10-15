<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->string('nome_negocio');
            $table->text('descricao')->nullable();
            $table->enum('tom', ['formal', 'neutro', 'informal'])->default('neutro');
            $table->json('horarios')->nullable();
            // Estruturado de propósito: é o que permite conferir, depois da
            // resposta, que o modelo não inventou preço.
            $table->json('produtos')->nullable();
            $table->json('faqs')->nullable();
            $table->text('nunca_faca')->nullable();
            $table->text('quando_humano')->nullable();
            $table->text('saudacao')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};

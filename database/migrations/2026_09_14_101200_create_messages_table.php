<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('tenant_id');
            $table->enum('direcao', ['entrada', 'saida']);
            $table->string('tipo')->default('texto');
            $table->string('waha_message_id')->nullable()->index();
            $table->text('conteudo')->nullable();
            $table->text('transcricao')->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_saida')->default(0);
            $table->decimal('custo_usd', 12, 8)->default(0);
            $table->string('modelo')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->foreign('conversation_id')->references('id')->on('conversations')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

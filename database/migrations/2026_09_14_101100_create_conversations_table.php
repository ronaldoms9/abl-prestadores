<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('chat_id');
            $table->enum('estado', ['bot', 'humano', 'pausada'])->default('bot');
            $table->dateTime('pausada_ate')->nullable();
            // Mensagem nova gera token novo: é assim que a geração em curso é
            // cancelada sem depender de cancelamento de job.
            $table->uuid('debounce_token')->nullable();
            $table->dateTime('ultima_mensagem_at')->nullable();
            $table->dateTime('expurgo_em')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'chat_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracao_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direcao', 10);
            $table->nullableMorphs('entidade');
            $table->string('operacao', 60);
            $table->string('rota', 120);
            $table->string('chave_idempotencia', 100)->nullable()->index();
            $table->json('payload')->nullable();
            $table->json('resposta')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('status', 10);
            $table->unsignedTinyInteger('tentativas')->default(1);
            $table->text('erro')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->timestamp('executado_em', 3)->index();
            $table->timestamps();

            $table->index(['status', 'executado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracao_logs');
    }
};

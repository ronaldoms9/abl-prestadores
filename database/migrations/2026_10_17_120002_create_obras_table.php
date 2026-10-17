<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('uau_codigo', 20);
            $table->string('descricao');
            $table->smallInteger('status_uau')->nullable();
            $table->string('tipo_uau', 20)->nullable();
            $table->json('endereco')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('sincronizado_em')->nullable();
            $table->auditoria();

            $table->unique(['empresa_id', 'uau_codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras');
    }
};

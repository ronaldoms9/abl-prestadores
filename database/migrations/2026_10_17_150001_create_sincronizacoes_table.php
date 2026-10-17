<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sincronizacoes', function (Blueprint $table) {
            $table->id();
            $table->string('origem', 10);
            $table->string('status', 12);
            $table->timestamp('iniciada_em');
            $table->timestamp('concluida_em')->nullable();
            $table->text('erro')->nullable();
            $table->auditoria();

            $table->index(['status', 'iniciada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronizacoes');
    }
};

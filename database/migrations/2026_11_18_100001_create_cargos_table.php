<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->text('descricao')->nullable();
            $table->decimal('faixa_min', 12, 2)->nullable();
            $table->decimal('faixa_max', 12, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uau_mapeamentos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->string('chave_local', 100);
            $table->string('codigo_uau', 50);
            $table->auditoria();

            $table->unique(['tipo', 'chave_local']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uau_mapeamentos');
    }
};

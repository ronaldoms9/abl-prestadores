<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Índice da subconsulta de "falha resolvida" (D8.3 da fatia 8). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integracao_logs', function (Blueprint $table) {
            $table->index(['operacao', 'status', 'executado_em'], 'integracao_logs_resolucao_index');
        });
    }

    public function down(): void
    {
        Schema::table('integracao_logs', function (Blueprint $table) {
            $table->dropIndex('integracao_logs_resolucao_index');
        });
    }
};

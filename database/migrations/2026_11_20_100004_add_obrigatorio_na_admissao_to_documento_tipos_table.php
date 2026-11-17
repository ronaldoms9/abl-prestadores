<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca os tipos de documento que a admissão exige. O item automático do
 * checklist "documentos obrigatórios enviados" olha só estes (E3, fatia 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_tipos', function (Blueprint $table) {
            $table->boolean('obrigatorio_na_admissao')->default(false)->after('exige_validade');
        });
    }

    public function down(): void
    {
        Schema::table('documento_tipos', function (Blueprint $table) {
            $table->dropColumn('obrigatorio_na_admissao');
        });
    }
};

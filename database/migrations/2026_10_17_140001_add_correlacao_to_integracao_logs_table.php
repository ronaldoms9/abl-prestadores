<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador enviado ao UAU no header X-Correlation-ID, para localizar uma
 * chamada junto ao suporte da Globaltec (prática herdada da integração do SGTR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integracao_logs', function (Blueprint $table) {
            $table->uuid('correlacao')->nullable()->after('chave_idempotencia')->index();
        });
    }

    public function down(): void
    {
        Schema::table('integracao_logs', function (Blueprint $table) {
            $table->dropIndex(['correlacao']);
            $table->dropColumn('correlacao');
        });
    }
};

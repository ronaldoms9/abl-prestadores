<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Antecedência dos alertas, numeração de contratos e outros parâmetros.
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->json('valor')->nullable();
            $table->auditoria();
        });

        // Detecta conflito na gravação no UAU (fatia 4): o que o UAU tinha na última leitura.
        foreach (['pessoas_fisicas', 'pessoas_juridicas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->timestamp('uau_alterado_em')->nullable()->after('sincronizado_em');
            });
        }
    }

    public function down(): void
    {
        foreach (['pessoas_fisicas', 'pessoas_juridicas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('uau_alterado_em');
            });
        }
        Schema::dropIfExists('configuracoes');
    }
};

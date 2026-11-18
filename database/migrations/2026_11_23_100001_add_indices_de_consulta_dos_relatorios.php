<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para as consultas do fechamento, do painel e dos relatórios (E6, fatia 4):
 * quem esteve ativo em um mês filtra por data de admissão e de desligamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->index('data_admissao', 'colaboradores_data_admissao_index');
            $table->index('data_desligamento', 'colaboradores_data_desligamento_index');
        });
    }

    public function down(): void
    {
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->dropIndex('colaboradores_data_admissao_index');
            $table->dropIndex('colaboradores_data_desligamento_index');
        });
    }
};

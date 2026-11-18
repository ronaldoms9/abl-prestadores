<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas fiscais e títulos a pagar (E5). Uma nota por fechamento; reenvio de
 * nota recusada reaproveita a linha. A chave de acesso é única; sem chave, a
 * duplicidade por CNPJ + número + série é validada na ação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->foreignId('fechamento_id')->constrained('fechamentos')->restrictOnDelete();
            $table->string('numero', 30)->nullable();
            $table->string('serie', 10)->nullable();
            $table->string('chave_acesso', 60)->nullable()->unique();
            $table->date('data_emissao')->nullable();
            $table->string('cnpj_emissor', 14)->nullable();
            $table->string('cnpj_tomador', 14)->nullable();
            $table->decimal('valor_bruto', 12, 2)->nullable();
            $table->decimal('valor_retencoes', 12, 2)->nullable();
            $table->decimal('valor_liquido', 12, 2)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 20)->default('enviada');
            $table->string('origem_dados', 10)->default('xml');
            $table->string('motivo_recusa')->nullable();
            $table->json('divergencias')->nullable();
            $table->string('justificativa_aprovacao')->nullable();
            $table->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable();
            $table->auditoria();
            $table->unique('fechamento_id');
            $table->index(['competencia_id', 'status']);
            $table->index(['cnpj_emissor', 'numero', 'serie']);
        });

        Schema::create('nota_retencoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $table->string('tributo', 10);
            $table->decimal('valor', 12, 2);
            $table->auditoria();
            $table->index('nota_fiscal_id');
        });

        Schema::create('titulos_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fechamento_id')->constrained('fechamentos')->restrictOnDelete();
            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->restrictOnDelete();
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->string('uau_processo')->nullable();
            $table->string('status', 20)->default('pendente');
            $table->string('chave_idempotencia')->unique();
            $table->auditoria();
            $table->unique('fechamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titulos_pagar');
        Schema::dropIfExists('nota_retencoes');
        Schema::dropIfExists('notas_fiscais');
    }
};

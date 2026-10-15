<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sobrevive ao expurgo de 90 dias: métrica agregada não é dado pessoal.
        Schema::create('metrics_daily', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->date('data');
            $table->unsignedInteger('conversas')->default(0);
            $table->unsignedInteger('resolvidas')->default(0);
            $table->unsignedInteger('fora_horario')->default(0);
            $table->unsignedInteger('escaladas')->default(0);
            $table->unsignedBigInteger('tokens_entrada')->default(0);
            $table->unsignedBigInteger('tokens_saida')->default(0);
            $table->decimal('custo_usd', 12, 8)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'data']);
            $table->foreign('tenant_id')->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_daily');
    }
};

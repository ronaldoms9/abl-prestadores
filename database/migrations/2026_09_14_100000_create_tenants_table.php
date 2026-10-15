<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nome');
            $table->string('slug')->unique();
            $table->date('ativo_ate')->nullable();
            $table->string('waha_instance')->default('waha-1');
            $table->string('waha_session')->nullable()->unique();
            $table->unsignedInteger('teto_mensagens_mes')->default(3000);
            $table->unsignedInteger('mensagens_mes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

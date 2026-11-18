<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fluxos de aprovação de férias por colaborador, equipe e função (E4, fatia 9).
 * Os níveis que já existiam viram o fluxo padrão, e as solicitações já feitas
 * ficam presas a ele, então nada muda para quem já usa o sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ferias_fluxos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('escopo', 20);
            $table->foreignId('equipe_id')->nullable()->constrained('equipes')->nullOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->foreignId('colaborador_id')->nullable()->constrained('colaboradores')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->auditoria();
            $table->index(['escopo', 'ativo']);
        });

        Schema::table('ferias_niveis', function (Blueprint $table) {
            $table->foreignId('fluxo_id')->nullable()->after('id')->constrained('ferias_fluxos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->after('permission_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('ferias_solicitacoes', function (Blueprint $table) {
            $table->foreignId('fluxo_id')->nullable()->after('colaborador_id')->constrained('ferias_fluxos')->nullOnDelete();
        });

        $agora = now();
        $padrao = DB::table('ferias_fluxos')->insertGetId([
            'nome' => 'Fluxo padrão', 'escopo' => 'padrao', 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        DB::table('ferias_niveis')->update(['fluxo_id' => $padrao]);
        DB::table('ferias_solicitacoes')->update(['fluxo_id' => $padrao]);
    }

    public function down(): void
    {
        Schema::table('ferias_solicitacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fluxo_id');
        });
        Schema::table('ferias_niveis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('fluxo_id');
        });
        Schema::dropIfExists('ferias_fluxos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colaborador em várias obras, com rateio por percentual e vigência, e o rateio
 * gravado em cada fechamento (E4, fatia 10). Quem já tinha obra passa a ter 100%
 * nela, e os fechamentos já calculados ganham uma linha de 100% na obra do colaborador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaborador_obras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->decimal('percentual', 5, 2);
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->auditoria();
            $table->index(['colaborador_id', 'fim']);
            $table->index(['obra_id', 'fim']);
        });

        Schema::create('fechamento_rateios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fechamento_id')->constrained('fechamentos')->cascadeOnDelete();
            $table->foreignId('obra_id')->nullable()->constrained('obras')->nullOnDelete();
            $table->decimal('percentual', 7, 4);
            $table->decimal('valor_remuneracao', 12, 2)->default(0);
            $table->decimal('total_beneficios', 12, 2)->default(0);
            $table->decimal('total_ajustes', 12, 2)->default(0);
            $table->decimal('total_liquido', 12, 2)->default(0);
            $table->timestamps();
            $table->index('fechamento_id');
            $table->index('obra_id');
        });

        $agora = now();

        DB::table('colaboradores')->whereNotNull('obra_id')->whereNull('deleted_at')
            ->select(['id', 'obra_id', 'data_admissao'])->orderBy('id')
            ->each(function ($c) use ($agora) {
                DB::table('colaborador_obras')->insert([
                    'colaborador_id' => $c->id, 'obra_id' => $c->obra_id, 'percentual' => 100,
                    'inicio' => $c->data_admissao ?? $agora->toDateString(), 'fim' => null,
                    'created_at' => $agora, 'updated_at' => $agora,
                ]);
            });

        DB::table('fechamentos')->join('colaboradores', 'colaboradores.id', '=', 'fechamentos.colaborador_id')
            ->whereNull('fechamentos.deleted_at')
            ->select(['fechamentos.id', 'colaboradores.obra_id', 'fechamentos.valor_remuneracao', 'fechamentos.total_beneficios', 'fechamentos.total_ajustes', 'fechamentos.total_liquido'])
            ->orderBy('fechamentos.id')
            ->each(function ($f) use ($agora) {
                DB::table('fechamento_rateios')->insert([
                    'fechamento_id' => $f->id, 'obra_id' => $f->obra_id, 'percentual' => 100,
                    'valor_remuneracao' => $f->valor_remuneracao, 'total_beneficios' => $f->total_beneficios,
                    'total_ajustes' => $f->total_ajustes, 'total_liquido' => $f->total_liquido,
                    'created_at' => $agora, 'updated_at' => $agora,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamento_rateios');
        Schema::dropIfExists('colaborador_obras');
    }
};

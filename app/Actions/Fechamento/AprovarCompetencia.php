<?php

namespace App\Actions\Fechamento;

use App\Enums\StatusCompetencia;
use App\Enums\StatusFechamento;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aprovação pelo financeiro: grava o snapshot de cada fechamento, leva os
 * fechamentos para "aguardando nota" e torna a competência imutável.
 */
class AprovarCompetencia
{
    public function executar(Competencia $competencia, User $aprovador, bool $ignorarAvisos = false): Competencia
    {
        return DB::transaction(function () use ($competencia, $aprovador, $ignorarAvisos) {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($competencia->id);

            if ($competencia->status !== StatusCompetencia::EmConferencia) {
                throw ValidationException::withMessages(['competencia' => 'Só dá para aprovar uma competência em conferência.']);
            }

            $fechamentos = $competencia->fechamentos()->with(['itens', 'rateios', 'colaborador.pessoaFisica'])->get();

            if ($fechamentos->isEmpty()) {
                throw ValidationException::withMessages(['competencia' => 'A competência não tem nenhum fechamento para aprovar.']);
            }

            $comAviso = $fechamentos->filter(fn ($f) => ! empty($f->snapshot['avisos'] ?? []));
            if ($comAviso->isNotEmpty() && ! $ignorarAvisos) {
                throw ValidationException::withMessages(['competencia' => $comAviso->count().' fechamento(s) com aviso de cálculo ('.$comAviso->take(3)->map(fn ($f) => $f->colaborador->pessoaFisica->nome)->implode(', ').'). Corrija ou confirme a aprovação mesmo assim.']);
            }

            $regras = [
                'base_proporcional' => Configuracao::obter('calculo.base_proporcional', 'corridos'),
                'sabado_trabalhado' => (bool) Configuracao::obter('calculo.sabado_trabalhado', false),
                'beneficio_fixo_proporcional' => (bool) Configuracao::obter('calculo.beneficio_fixo_proporcional', true),
            ];

            foreach ($fechamentos as $f) {
                $f->update([
                    'status' => StatusFechamento::AguardandoNota,
                    'snapshot' => [
                        'avisos' => $f->snapshot['avisos'] ?? [],
                        'periodo' => $f->snapshot['periodo'] ?? null,
                        'dias_periodo' => $f->dias_periodo,
                        'dias_trabalhados' => $f->dias_trabalhados,
                        'valor_remuneracao' => $f->valor_remuneracao,
                        'total_beneficios' => $f->total_beneficios,
                        'total_ajustes' => $f->total_ajustes,
                        'total_liquido' => $f->total_liquido,
                        'rateio' => $f->rateios->map(fn ($r) => [
                            'obra_id' => $r->obra_id,
                            'percentual' => $r->percentual,
                            'valor_remuneracao' => $r->valor_remuneracao,
                            'total_beneficios' => $r->total_beneficios,
                            'total_ajustes' => $r->total_ajustes,
                            'total_liquido' => $r->total_liquido,
                        ])->all(),
                        'itens' => $f->itens->map(fn ($i) => [
                            'tipo' => $i->tipo->value,
                            'descricao' => $i->descricao,
                            'quantidade' => $i->quantidade,
                            'valor_unitario' => $i->valor_unitario,
                            'valor_total' => $i->valor_total,
                        ])->all(),
                        'regras' => $regras,
                        'aprovado_por' => $aprovador->id,
                        'aprovado_em' => now()->toIso8601String(),
                    ],
                ]);
            }

            $competencia->update(['status' => StatusCompetencia::Aprovada, 'aprovada_por' => $aprovador->id, 'aprovada_em' => now()]);
            Auditoria::registrar('competencia-aprovada', $competencia, ['fechamentos' => $fechamentos->count(), 'total' => number_format((float) $fechamentos->sum('total_liquido'), 2, '.', ''), 'avisos_ignorados' => $comAviso->count()]);

            return $competencia;
        });
    }
}

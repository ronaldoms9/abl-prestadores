<?php

namespace App\Actions\Ferias;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\FeriasPeriodo;
use App\Support\Auditoria;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera os períodos aquisitivos de férias de um colaborador ativo: 12 meses
 * cada, contados da admissão e sem folga entre eles. O período corrente existe
 * desde a admissão (os dias vão se acumulando); o seguinte abre no dia em que o
 * anterior completa os 12 meses. Admissão antiga gera todos os períodos que já
 * começaram. Pode rodar de novo: só cria o que falta.
 *
 * Cada período guarda o direito vigente na criação (padrão 30 dias, em
 * `configuracoes[ferias.dias_direito]`) e o limite de gozo: o fim do período
 * mais `ferias.limite_gozo_meses` (padrão 12).
 */
class GerarPeriodosAquisitivos
{
    /** @return Collection<int, FeriasPeriodo> Só os períodos criados agora. */
    public function executar(Colaborador $colaborador, ?CarbonInterface $hoje = null): Collection
    {
        $hoje = ($hoje ?? now())->copy()->startOfDay();

        if ($colaborador->status !== StatusColaborador::Ativo || $colaborador->data_admissao === null) {
            return collect();
        }

        $admissao = $colaborador->data_admissao->copy()->startOfDay();
        $direito = $this->numero('ferias.dias_direito', 'dias_direito');
        $mesesGozo = $this->numero('ferias.limite_gozo_meses', 'limite_gozo_meses');

        return DB::transaction(function () use ($colaborador, $hoje, $admissao, $direito, $mesesGozo) {
            $existentes = FeriasPeriodo::withTrashed()->where('colaborador_id', $colaborador->id)->pluck('aquisitivo_inicio')->map(fn ($d) => $d->toDateString())->all();
            $criados = collect();

            for ($n = 0; ; $n++) {
                $inicio = $admissao->copy()->addMonthsNoOverflow(12 * $n);
                if ($inicio->gt($hoje)) {
                    break;
                }

                if (in_array($inicio->toDateString(), $existentes, true)) {
                    continue;
                }

                $fim = $admissao->copy()->addMonthsNoOverflow(12 * ($n + 1))->subDay();

                $criados->push(FeriasPeriodo::create([
                    'colaborador_id' => $colaborador->id,
                    'aquisitivo_inicio' => $inicio,
                    'aquisitivo_fim' => $fim,
                    'dias_direito' => $direito,
                    'dias_utilizados' => 0,
                    'limite_gozo' => $fim->copy()->addMonthsNoOverflow($mesesGozo),
                ]));
            }

            if ($criados->isNotEmpty()) {
                Auditoria::registrar('ferias-periodos-gerados', $colaborador, ['periodos' => $criados->count(), 'ate' => $criados->last()->aquisitivo_inicio->toDateString()]);
            }

            return $criados;
        });
    }

    private function numero(string $chave, string $padrao): int
    {
        $valor = Configuracao::obter($chave);

        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : (int) config("cadastros.ferias.{$padrao}");
    }
}

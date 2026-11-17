<?php

namespace App\Listeners;

use App\Actions\Contratos\CriarRascunhoDeAditivo;
use App\Enums\MotivoAditivo;
use App\Events\ReajusteAplicado;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Dinheiro;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Depois de um reajuste (individual ou em lote), cria o rascunho do aditivo de
 * cada colaborador reajustado que tem contrato em vigor (E3, fatia 9). Roda em
 * fila: um lote grande não trava a tela de quem reajustou. Quem não tem contrato
 * ou modelo é contado no log; o RH vê os rascunhos na tela de aditivos pendentes.
 */
class GerarAditivosDeReajuste implements ShouldQueue
{
    public function __construct(private readonly CriarRascunhoDeAditivo $criar)
    {
    }

    public function handle(ReajusteAplicado $evento): void
    {
        $linhas = Remuneracao::query()->whereIn('id', $evento->remuneracoes->pluck('id'))->get();
        $colaboradores = Colaborador::query()->whereIn('id', $linhas->pluck('colaborador_id'))->get()->keyBy('id');
        $anteriores = Remuneracao::query()
            ->whereIn('colaborador_id', $linhas->pluck('colaborador_id'))
            ->whereNotNull('vigencia_fim')
            ->get()
            ->groupBy('colaborador_id');

        $resumo = ['criados' => 0];

        foreach ($linhas as $linha) {
            $anterior = ($anteriores[$linha->colaborador_id] ?? collect())->first(fn (Remuneracao $r) => $r->vigencia_fim->equalTo($linha->vigencia_inicio));

            $resultado = $this->criar->executar(
                $colaboradores[$linha->colaborador_id],
                MotivoAditivo::Reajuste,
                $linha->vigencia_inicio,
                [
                    'reajuste_id' => $evento->reajuste->id,
                    'valor_anterior' => $anterior ? Dinheiro::centavos($anterior->valor_mensal) : null,
                    'valor_novo' => Dinheiro::centavos($linha->valor_mensal),
                ],
                'reajuste:'.$evento->reajuste->id,
            );

            $chave = $resultado['rascunho'] ? 'criados' : $resultado['motivo'];
            $resumo[$chave] = ($resumo[$chave] ?? 0) + 1;
        }

        Log::info('Aditivos de reajuste', ['reajuste_id' => $evento->reajuste->id] + $resumo);
    }
}

<?php

namespace App\Actions\Fechamento;

use App\Enums\StatusFechamento;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Models\FechamentoItem;
use App\Services\Fechamento\CalculadoraFechamento;
use App\Services\Fechamento\ItemCalculado;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calcula e grava o fechamento de um colaborador na competência (cria ou
 * substitui itens e totais). Recusa competência imutável. Devolve null, e
 * apaga o fechamento que existia, se o colaborador não esteve ativo no mês.
 */
class CalcularFechamentoColaborador
{
    public function __construct(private readonly CalculadoraFechamento $calculadora)
    {
    }

    public function executar(Colaborador $colaborador, Competencia $competencia): ?Fechamento
    {
        return DB::transaction(function () use ($colaborador, $competencia) {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($competencia->id);

            if ($competencia->status->imutavel()) {
                throw ValidationException::withMessages(['competencia' => 'A competência de '.$this->mes($competencia).' já foi aprovada; os valores não mudam mais.']);
            }

            $existente = Fechamento::query()->where('competencia_id', $competencia->id)->where('colaborador_id', $colaborador->id)->first();
            $resultado = $this->calculadora->calcular($colaborador, $competencia);

            if ($resultado === null) {
                if ($existente) {
                    FechamentoItem::query()->where('fechamento_id', $existente->id)->delete();
                    $existente->delete();
                }

                return null;
            }

            $fechamento = $existente ?? new Fechamento(['competencia_id' => $competencia->id, 'colaborador_id' => $colaborador->id]);
            $fechamento->fill([
                'dias_periodo' => $resultado->diasPeriodo,
                'dias_trabalhados' => $resultado->diasTrabalhados,
                'valor_remuneracao' => Dinheiro::decimal($resultado->remuneracao()),
                'total_beneficios' => Dinheiro::decimal($resultado->beneficios()),
                'total_ajustes' => Dinheiro::decimal($resultado->ajustes()),
                'total_liquido' => Dinheiro::decimal($resultado->liquido()),
                'status' => StatusFechamento::Calculado,
                'snapshot' => ['avisos' => $resultado->avisos, 'periodo' => [$resultado->inicio->toDateString(), $resultado->fim->toDateString()]],
            ])->save();

            FechamentoItem::query()->where('fechamento_id', $fechamento->id)->delete();
            foreach ($resultado->itens as $item) {
                $this->gravarItem($fechamento, $item);
            }

            return $fechamento->load('itens');
        });
    }

    private function gravarItem(Fechamento $fechamento, ItemCalculado $item): void
    {
        $linha = new FechamentoItem([
            'fechamento_id' => $fechamento->id,
            'tipo' => $item->tipo,
            'descricao' => $item->descricao,
            'quantidade' => number_format($item->quantidade, 2, '.', ''),
            'valor_unitario' => Dinheiro::decimal($item->valorUnitario),
            'valor_total' => Dinheiro::decimal($item->total),
        ]);

        if ($item->origem !== null) {
            $linha->origem()->associate($item->origem);
        }

        $linha->save();
    }

    private function mes(Competencia $competencia): string
    {
        return substr($competencia->ano_mes, 5, 2).'/'.substr($competencia->ano_mes, 0, 4);
    }
}

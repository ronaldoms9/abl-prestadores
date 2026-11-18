<?php

namespace App\Actions\Fechamento;

use App\Enums\TipoAjuste;
use App\Models\AjusteAvulso;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Support\Auditoria;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Crédito ou débito avulso do colaborador no mês, com justificativa; recalcula o fechamento dele. */
class LancarAjusteAvulso
{
    public function __construct(private readonly CalcularFechamentoColaborador $calcular)
    {
    }

    public function executar(Competencia $competencia, Colaborador $colaborador, TipoAjuste $tipo, string|int|float $valor, string $justificativa, ?int $usuarioId = null): AjusteAvulso
    {
        $centavos = Dinheiro::centavos($valor);
        $justificativa = trim($justificativa);

        if ($centavos <= 0) {
            throw ValidationException::withMessages(['valor' => 'Informe um valor maior que zero.']);
        }
        if ($justificativa === '') {
            throw ValidationException::withMessages(['justificativa' => 'A justificativa é obrigatória.']);
        }

        return DB::transaction(function () use ($competencia, $colaborador, $tipo, $centavos, $justificativa, $usuarioId) {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($competencia->id);
            $this->exigirAberta($competencia);

            if (! Fechamento::query()->where('competencia_id', $competencia->id)->where('colaborador_id', $colaborador->id)->exists()) {
                throw ValidationException::withMessages(['colaborador' => 'Este colaborador não tem fechamento nesta competência.']);
            }

            $ajuste = AjusteAvulso::create([
                'colaborador_id' => $colaborador->id,
                'competencia_id' => $competencia->id,
                'tipo' => $tipo,
                'valor' => Dinheiro::decimal($centavos),
                'justificativa' => $justificativa,
                'aprovado_por' => $usuarioId,
            ]);

            $this->calcular->executar($colaborador, $competencia);

            return $ajuste;
        });
    }

    public function remover(AjusteAvulso $ajuste): void
    {
        DB::transaction(function () use ($ajuste) {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($ajuste->competencia_id);
            $this->exigirAberta($competencia);

            $colaborador = Colaborador::findOrFail($ajuste->colaborador_id);
            $ajuste->delete();
            $this->calcular->executar($colaborador, $competencia);
            Auditoria::registrar('ajuste-avulso-removido', $competencia, ['colaborador_id' => $colaborador->id, 'valor' => $ajuste->valor, 'tipo' => $ajuste->tipo->value]);
        });
    }

    private function exigirAberta(Competencia $competencia): void
    {
        if ($competencia->status->imutavel()) {
            throw ValidationException::withMessages(['competencia' => 'A competência já foi aprovada; ajustes não são mais aceitos.']);
        }
    }
}

<?php

namespace App\Actions\Contratos;

use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria o rascunho de um contrato, aditivo ou distrato. O tipo vem do modelo.
 * Contrato novo exige que não haja outro gerado ou assinado em vigor nem outro
 * rascunho. Aditivo e distrato exigem o contrato pai do mesmo colaborador
 * (gerado ou assinado); só pode haver um distrato por contrato. Nada é
 * numerado aqui: o número sai na geração.
 */
class CriarContrato
{
    /**
     * @param  array<string, mixed>  $dados  Próprios de aditivo e distrato (valor anterior, novo cargo...).
     */
    public function executar(
        Colaborador $colaborador,
        ContratoModelo $modelo,
        ?CarbonInterface $vigenciaInicio,
        ?CarbonInterface $vigenciaFim = null,
        ?Contrato $pai = null,
        ?MotivoAditivo $motivo = null,
        array $dados = [],
    ): Contrato {
        $tipo = $modelo->tipo;

        if (! $modelo->ativo) {
            throw ValidationException::withMessages(['contrato_modelo_id' => 'O modelo está inativo.']);
        }

        if ($colaborador->status === StatusColaborador::Desligado && $tipo !== TipoContrato::Distrato) {
            throw ValidationException::withMessages(['colaborador' => 'O colaborador está desligado. Só o distrato pode ser gerado.']);
        }

        $inicio = $vigenciaInicio?->copy()->startOfDay();
        $fim = $vigenciaFim?->copy()->startOfDay();

        if ($tipo === TipoContrato::Contrato && $inicio === null) {
            throw ValidationException::withMessages(['vigencia_inicio' => 'Informe o início da vigência do contrato.']);
        }

        if ($inicio !== null && $fim !== null && $fim->lte($inicio)) {
            throw ValidationException::withMessages(['vigencia_fim' => 'O fim da vigência precisa ser posterior ao início.']);
        }

        return DB::transaction(function () use ($colaborador, $modelo, $tipo, $inicio, $fim, $pai, $motivo, $dados) {
            if ($tipo === TipoContrato::Contrato) {
                $this->validarContratoNovo($colaborador, $pai);
            } else {
                $this->validarComPai($colaborador, $tipo, $pai);
            }

            return Contrato::create([
                'colaborador_id' => $colaborador->id,
                'contrato_modelo_id' => $modelo->id,
                'contrato_pai_id' => $pai?->id,
                'tipo' => $tipo,
                'motivo' => $tipo === TipoContrato::Aditivo ? ($motivo ?? MotivoAditivo::Outro) : null,
                'dados' => $dados === [] ? null : $dados,
                'vigencia_inicio' => $inicio,
                'vigencia_fim' => $fim,
                'status' => StatusContrato::Rascunho,
            ]);
        });
    }

    private function validarContratoNovo(Colaborador $colaborador, ?Contrato $pai): void
    {
        if ($pai !== null) {
            throw ValidationException::withMessages(['contrato_pai_id' => 'Um contrato novo não tem contrato pai.']);
        }

        $existente = Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Rascunho->value, StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->lockForUpdate()
            ->first();

        if ($existente !== null) {
            throw ValidationException::withMessages(['colaborador' => $existente->status === StatusContrato::Rascunho
                ? 'Já existe um rascunho de contrato para este colaborador. Gere ou cancele o rascunho.'
                : 'O colaborador já tem o contrato '.$existente->numero.' em vigor. Para mudar condições, faça um aditivo.']);
        }
    }

    private function validarComPai(Colaborador $colaborador, TipoContrato $tipo, ?Contrato $pai): void
    {
        $rotulo = mb_strtolower($tipo->rotulo());

        if ($pai === null) {
            throw ValidationException::withMessages(['contrato_pai_id' => "Escolha o contrato ao qual o {$rotulo} se refere."]);
        }

        $pai = Contrato::query()->lockForUpdate()->findOrFail($pai->id);

        if ($pai->colaborador_id !== $colaborador->id || $pai->tipo !== TipoContrato::Contrato) {
            throw ValidationException::withMessages(['contrato_pai_id' => 'O contrato escolhido não é um contrato deste colaborador.']);
        }

        if (! in_array($pai->status, [StatusContrato::Gerado, StatusContrato::Assinado], true)) {
            throw ValidationException::withMessages(['contrato_pai_id' => "O contrato precisa estar gerado ou assinado para ter {$rotulo}."]);
        }

        if ($tipo === TipoContrato::Distrato && Contrato::query()->where('contrato_pai_id', $pai->id)->where('tipo', TipoContrato::Distrato->value)->exists()) {
            throw ValidationException::withMessages(['contrato_pai_id' => 'Este contrato já tem distrato.']);
        }
    }
}

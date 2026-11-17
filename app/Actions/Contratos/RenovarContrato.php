<?php

namespace App\Actions\Contratos;

use App\Enums\MotivoAditivo;
use App\Enums\TipoContrato;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Renovar: cria o rascunho do aditivo de prorrogação com a nova data de fim.
 * O aditivo usa o modelo ativo de aditivo para "prorrogação" (ou o genérico) e
 * começa no dia seguinte ao fim atual. Quem gera o PDF é o fluxo normal
 * (GerarContrato). Só contrato com prazo e ainda em vigor se renova.
 */
class RenovarContrato
{
    public function __construct(
        private readonly VigenciaEfetiva $vigencia,
        private readonly CriarContrato $criar,
    ) {
    }

    public function executar(Contrato $contrato, CarbonInterface $novoFim): Contrato
    {
        if ($contrato->tipo !== TipoContrato::Contrato) {
            throw ValidationException::withMessages(['contrato' => 'Só um contrato pode ser renovado.']);
        }

        $fimAtual = $this->vigencia->fim($contrato);
        if ($fimAtual === null) {
            throw ValidationException::withMessages(['vigencia_fim' => 'Este contrato não tem data de fim: não há o que prorrogar.']);
        }

        $novoFim = $novoFim->copy()->startOfDay();
        if ($novoFim->lte($fimAtual)) {
            throw ValidationException::withMessages(['vigencia_fim' => 'A nova data de fim precisa ser posterior a '.$fimAtual->format('d/m/Y').', fim atual do contrato.']);
        }

        $modelo = ContratoModelo::aditivoPara(MotivoAditivo::Prorrogacao);
        if ($modelo === null) {
            throw ValidationException::withMessages(['contrato_modelo_id' => 'Não há modelo ativo de aditivo para prorrogação. Cadastre um em Contratos > Modelos.']);
        }

        return $this->criar->executar(
            $contrato->colaborador,
            $modelo,
            $fimAtual->copy()->addDay(),
            $novoFim,
            $contrato,
            MotivoAditivo::Prorrogacao,
            ['nova_vigencia_fim' => $novoFim->toDateString(), 'vigencia_fim_anterior' => $fimAtual->toDateString()],
        );
    }
}

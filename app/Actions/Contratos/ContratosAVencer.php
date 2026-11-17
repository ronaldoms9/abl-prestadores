<?php

namespace App\Actions\Contratos;

use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Contrato;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Contratos que pedem atenção hoje, para o alerta diário e a tela do
 * colaborador. Conta o fim com as prorrogações. Contrato sem fim (prazo
 * indeterminado), de colaborador desligado ou já encerrado não entra.
 *
 * - vencidos: o último dia já passou (todo dia até renovar ou encerrar);
 * - marcos: faltam exatamente N dias, para N nos marcos (30, 15 e 5 por
 *   padrão) ou 0 (vence hoje).
 */
class ContratosAVencer
{
    public function __construct(private readonly VigenciaEfetiva $vigencia)
    {
    }

    /**
     * @param  array<int, int>  $marcos
     * @return array{vencidos: Collection<int, array{contrato: Contrato, fim: CarbonInterface, dias: int}>, marcos: Collection<int, array{contrato: Contrato, fim: CarbonInterface, dias: int}>}
     */
    public function consultar(array $marcos, CarbonInterface $hoje): array
    {
        $dia = $hoje->copy()->startOfDay();

        $contratos = Contrato::query()
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->whereHas('colaborador', fn ($q) => $q->where('status', '!=', StatusColaborador::Desligado->value))
            ->with(['colaborador.pessoaFisica', 'filhos' => fn ($q) => $q->where('tipo', TipoContrato::Aditivo->value)])
            ->get();

        $vencidos = collect();
        $proximos = collect();

        foreach ($contratos as $contrato) {
            $fim = $this->vigencia->dosFilhos($contrato, $contrato->filhos);
            if ($fim === null) {
                continue;
            }

            $dias = (int) $dia->diffInDays($fim, false);
            $item = ['contrato' => $contrato, 'fim' => $fim, 'dias' => $dias];

            if ($dias < 0) {
                $vencidos->push($item);
            } elseif ($dias === 0 || in_array($dias, $marcos, true)) {
                $proximos->push($item);
            }
        }

        return [
            'vencidos' => $vencidos->sortBy('dias')->values(),
            'marcos' => $proximos->sortBy('dias')->values(),
        ];
    }
}

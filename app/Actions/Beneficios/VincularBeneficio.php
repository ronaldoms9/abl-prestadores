<?php

namespace App\Actions\Beneficios;

use App\Enums\StatusColaborador;
use App\Models\Beneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Support\Auditoria;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dá um benefício ao colaborador a partir de uma data, com valor personalizado
 * opcional (sem ele vale a tabela vigente). Recusa colaborador desligado ou
 * início depois do desligamento, benefício inativo, início em competência
 * fechada e período que se sobreponha a outro vínculo do mesmo benefício.
 * `fim` é exclusivo, como nas demais vigências.
 */
class VincularBeneficio
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    public function executar(
        Colaborador $colaborador,
        Beneficio $beneficio,
        CarbonInterface $inicio,
        string|int|float|null $valorPersonalizado = null,
        ?CarbonInterface $fim = null,
    ): ColaboradorBeneficio {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim?->copy()->startOfDay();
        $centavos = ($valorPersonalizado === null || $valorPersonalizado === '') ? null : Dinheiro::centavos($valorPersonalizado);

        if ($colaborador->status === StatusColaborador::Desligado) {
            throw ValidationException::withMessages(['colaborador' => 'O colaborador está desligado.']);
        }

        if ($colaborador->data_desligamento !== null && $inicio->gt($colaborador->data_desligamento)) {
            throw ValidationException::withMessages(['inicio' => 'O benefício não pode começar depois do último dia de trabalho ('.$colaborador->data_desligamento->format('d/m/Y').').']);
        }

        if (! $beneficio->ativo) {
            throw ValidationException::withMessages(['beneficio_id' => 'O benefício está inativo.']);
        }

        if ($centavos !== null && $centavos <= 0) {
            throw ValidationException::withMessages(['valor_personalizado' => 'O valor personalizado precisa ser maior que zero.']);
        }

        if ($fim !== null && $fim->lte($inicio)) {
            throw ValidationException::withMessages(['fim' => 'O fim precisa ser posterior ao início.']);
        }

        if ($this->competencias->fechada($inicio)) {
            throw ValidationException::withMessages(['inicio' => 'A competência de '.$inicio->format('m/Y').' já está fechada. Vínculo retroativo não é permitido.']);
        }

        return DB::transaction(function () use ($colaborador, $beneficio, $inicio, $fim, $centavos) {
            $sobreposto = ColaboradorBeneficio::query()
                ->where('colaborador_id', $colaborador->id)
                ->where('beneficio_id', $beneficio->id)
                ->lockForUpdate()
                ->get()
                ->contains(fn (ColaboradorBeneficio $outro) => ($fim === null || $outro->inicio->lt($fim))
                    && ($outro->fim === null || $outro->fim->gt($inicio)));

            if ($sobreposto) {
                throw ValidationException::withMessages(['beneficio_id' => 'O colaborador já tem este benefício no período. Encerre o vínculo atual antes de criar outro.']);
            }

            $vinculo = ColaboradorBeneficio::create([
                'colaborador_id' => $colaborador->id,
                'beneficio_id' => $beneficio->id,
                'valor_personalizado' => $centavos === null ? null : Dinheiro::decimal($centavos),
                'inicio' => $inicio,
                'fim' => $fim,
            ]);

            Auditoria::registrar('colaborador-beneficio-vinculado', $colaborador, [
                'beneficio' => $beneficio->nome,
                'inicio' => $inicio->toDateString(),
                'valor_personalizado' => $vinculo->valor_personalizado,
            ]);

            return $vinculo;
        });
    }
}

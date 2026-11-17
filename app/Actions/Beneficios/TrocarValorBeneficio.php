<?php

namespace App\Actions\Beneficios;

use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Único ponto que grava valor de benefício (mesma regra da remuneração,
 * README D5): a linha vigente nunca é editada. Ela é encerrada na data da nova
 * vigência (fim exclusivo) e uma linha nova é criada. Serve também para o valor
 * inicial, quando não há linha aberta.
 *
 * Roda dentro da transação de quem chama. Recusa valor não positivo,
 * vigência em competência fechada e vigência que não seja posterior ao início
 * da linha vigente.
 */
class TrocarValorBeneficio
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    public function executar(Beneficio $beneficio, int $centavos, CarbonInterface $vigenciaInicio): BeneficioValor
    {
        $inicio = $vigenciaInicio->copy()->startOfDay();

        if ($centavos <= 0) {
            throw ValidationException::withMessages(['valor' => 'O valor do benefício precisa ser maior que zero.']);
        }

        if ($this->competencias->fechada($inicio)) {
            throw ValidationException::withMessages(['vigencia' => 'A competência de '.$inicio->format('m/Y').' já está fechada. Mudança de valor retroativa não é permitida.']);
        }

        $abertas = BeneficioValor::query()
            ->where('beneficio_id', $beneficio->id)
            ->vigentes()
            ->lockForUpdate()
            ->get();

        foreach ($abertas as $aberta) {
            if ($inicio->lte($aberta->vigencia_inicio)) {
                throw ValidationException::withMessages(['vigencia' => 'A nova vigência precisa ser posterior a '.$aberta->vigencia_inicio->format('d/m/Y').', início do valor atual.']);
            }
        }

        foreach ($abertas as $aberta) {
            $aberta->update(['vigencia_fim' => $inicio]);
        }

        return BeneficioValor::create([
            'beneficio_id' => $beneficio->id,
            'valor' => Dinheiro::decimal($centavos),
            'vigencia_inicio' => $inicio,
        ]);
    }
}

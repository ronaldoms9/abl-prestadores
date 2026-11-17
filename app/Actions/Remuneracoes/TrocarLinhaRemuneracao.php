<?php

namespace App\Actions\Remuneracoes;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Competencias\CompetenciaFechada;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Único ponto que grava remuneração (README D5): a linha vigente nunca é
 * editada. Ela é encerrada na data da nova vigência (fim exclusivo, então vale
 * até a véspera) e uma linha nova é criada, ligada ao reajuste quando houver.
 *
 * Roda dentro da transação de quem chama. Recusa valor não positivo,
 * colaborador desligado, vigência em competência fechada e vigência que não
 * seja posterior ao início da linha vigente.
 */
class TrocarLinhaRemuneracao
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    public function executar(Colaborador $colaborador, int $centavos, CarbonInterface $vigenciaInicio, ?int $reajusteId = null): Remuneracao
    {
        $inicio = $vigenciaInicio->copy()->startOfDay();

        if ($centavos <= 0) {
            throw ValidationException::withMessages(['valor' => 'A remuneração precisa ser maior que zero.']);
        }

        if ($colaborador->status === StatusColaborador::Desligado) {
            throw ValidationException::withMessages(['colaborador' => 'O colaborador está desligado.']);
        }

        if ($this->competencias->fechada($inicio)) {
            throw ValidationException::withMessages(['vigencia' => 'A competência de '.$inicio->format('m/Y').' já está fechada. Reajuste retroativo não é permitido.']);
        }

        $abertas = Remuneracao::query()
            ->where('colaborador_id', $colaborador->id)
            ->vigentes()
            ->lockForUpdate()
            ->get();

        foreach ($abertas as $aberta) {
            if ($inicio->lte($aberta->vigencia_inicio)) {
                throw ValidationException::withMessages(['vigencia' => 'A nova vigência precisa ser posterior a '.$aberta->vigencia_inicio->format('d/m/Y').', início da remuneração atual.']);
            }
        }

        foreach ($abertas as $aberta) {
            $aberta->update(['vigencia_fim' => $inicio]);
        }

        return Remuneracao::create([
            'colaborador_id' => $colaborador->id,
            'valor_mensal' => Dinheiro::decimal($centavos),
            'vigencia_inicio' => $inicio,
            'reajuste_id' => $reajusteId,
        ]);
    }
}

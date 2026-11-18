<?php

namespace App\Actions\Cadastros;

use App\Models\Colaborador;
use App\Models\ColaboradorObra;
use App\Models\Obra;
use App\Support\Auditoria;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Define em quais obras o colaborador atua e quanto de cada uma, a partir de uma data (E4, fatia 10).
 * As linhas vigentes na data são encerradas na véspera, as que começariam depois são
 * substituídas, e entram as novas. Os percentuais somam 100,00. A obra principal
 * (`colaboradores.obra_id`) passa a ser a de maior percentual quando o rateio já vale hoje.
 */
class DefinirRateioObras
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /**
     * @param  list<array{obra_id: int|string, percentual: int|float|string}>  $partes
     */
    public function executar(Colaborador $colaborador, array $partes, CarbonInterface $inicio): void
    {
        $inicio = $inicio->copy()->startOfDay();
        $normalizadas = $this->validar($colaborador, $partes, $inicio);

        DB::transaction(function () use ($colaborador, $normalizadas, $inicio) {
            ColaboradorObra::query()->where('colaborador_id', $colaborador->id)
                ->whereDate('inicio', '>=', $inicio->toDateString())->get()->each->delete();

            ColaboradorObra::query()->where('colaborador_id', $colaborador->id)
                ->whereDate('inicio', '<', $inicio->toDateString())
                ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>', $inicio->toDateString()))
                ->get()->each->update(['fim' => $inicio->toDateString()]);

            foreach ($normalizadas as ['obra_id' => $obraId, 'bp' => $bp]) {
                ColaboradorObra::create([
                    'colaborador_id' => $colaborador->id, 'obra_id' => $obraId,
                    'percentual' => number_format($bp / 100, 2, '.', ''), 'inicio' => $inicio, 'fim' => null,
                ]);
            }

            if ($inicio->lte(now())) {
                $principal = collect($normalizadas)->sortBy([['bp', 'desc'], ['obra_id', 'asc']])->first()['obra_id'];
                $colaborador->forceFill(['obra_id' => $principal])->save();
            }

            Auditoria::registrar('rateio-obras-definido', $colaborador, [
                'a_partir_de' => $inicio->toDateString(),
                'partes' => array_map(fn ($p) => ['obra_id' => $p['obra_id'], 'percentual' => number_format($p['bp'] / 100, 2, '.', '')], $normalizadas),
            ]);
        });
    }

    /** @return list<array{obra_id: int, bp: int}> */
    private function validar(Colaborador $colaborador, array $partes, CarbonInterface $inicio): array
    {
        $erro = fn (string $m) => ValidationException::withMessages(['obras' => $m]);

        if ($partes === []) {
            throw $erro('Informe ao menos uma obra.');
        }

        $normalizadas = [];
        foreach ($partes as $p) {
            $obraId = (int) ($p['obra_id'] ?? 0);
            $bp = (int) round(((float) str_replace(',', '.', (string) ($p['percentual'] ?? 0))) * 100);

            if ($obraId <= 0) {
                throw $erro('Escolha a obra em todas as linhas.');
            }
            if ($bp <= 0 || $bp > 10000) {
                throw $erro('O percentual de cada obra fica entre 0,01% e 100%.');
            }
            $normalizadas[] = ['obra_id' => $obraId, 'bp' => $bp];
        }

        if (count(array_unique(array_column($normalizadas, 'obra_id'))) !== count($normalizadas)) {
            throw $erro('Há obra repetida no rateio.');
        }

        if (array_sum(array_column($normalizadas, 'bp')) !== 10000) {
            throw $erro('Os percentuais precisam somar 100%.');
        }

        $obras = Obra::query()->whereIn('id', array_column($normalizadas, 'obra_id'))->get()->keyBy('id');
        foreach ($normalizadas as $p) {
            $obra = $obras->get($p['obra_id']);

            if ($obra === null) {
                throw $erro('Obra não encontrada.');
            }
            if ($colaborador->empresa_id !== null && $obra->empresa_id !== $colaborador->empresa_id) {
                throw $erro('A obra '.$obra->descricao.' não pertence à empresa do colaborador.');
            }
        }

        if ($colaborador->data_admissao !== null && $inicio->lt($colaborador->data_admissao)) {
            throw $erro('O rateio não pode começar antes da admissão ('.$colaborador->data_admissao->format('d/m/Y').').');
        }

        if ($this->competencias->fechada($inicio)) {
            throw $erro('O mês de '.$inicio->format('m/Y').' já foi fechado; o rateio só pode começar em um mês aberto.');
        }

        return $normalizadas;
    }
}

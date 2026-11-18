<?php

namespace App\Actions\Calendario;

use App\Enums\AbrangenciaFeriado;
use App\Models\Feriado;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Traz os feriados nacionais de um ano da BrasilAPI. Só acrescenta; nunca altera nem apaga. */
class ImportarFeriadosNacionais
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /** @return array{criados: int, existentes: int, em_competencia_fechada: int} */
    public function executar(int $ano): array
    {
        try {
            $resposta = Http::timeout(10)->acceptJson()->get('https://brasilapi.com.br/api/feriados/v1/'.$ano);
        } catch (Throwable) {
            throw ValidationException::withMessages(['ano' => 'Não foi possível consultar a BrasilAPI agora. Cadastre os feriados à mão ou tente de novo mais tarde.']);
        }

        if (! $resposta->successful() || ! is_array($resposta->json())) {
            throw ValidationException::withMessages(['ano' => 'A BrasilAPI não devolveu os feriados de '.$ano.'. Cadastre à mão ou tente de novo mais tarde.']);
        }

        $criados = $existentes = $fechados = 0;

        foreach ($resposta->json() as $item) {
            if (! isset($item['date'], $item['name'])) {
                continue;
            }

            $data = Carbon::parse($item['date'])->startOfDay();

            if ($this->competencias->fechada($data)) {
                $fechados++;

                continue;
            }

            $existe = Feriado::query()->whereDate('data', $data)->where('abrangencia', AbrangenciaFeriado::Nacional->value)->whereNull('obra_id')->exists();

            if ($existe) {
                $existentes++;

                continue;
            }

            Feriado::create(['data' => $data, 'descricao' => trim($item['name']), 'abrangencia' => AbrangenciaFeriado::Nacional, 'obra_id' => null]);
            $criados++;
        }

        return ['criados' => $criados, 'existentes' => $existentes, 'em_competencia_fechada' => $fechados];
    }
}

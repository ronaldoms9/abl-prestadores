<?php

namespace App\Actions\Ocorrencias;

use App\Enums\TipoOcorrencia;
use App\Models\Ocorrencia;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Falta ou afastamento de um colaborador. Recusa período que toque competência fechada. */
class SalvarOcorrencia
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /** @param  array{colaborador_id: int|string, tipo: string, inicio: string, fim: string, abona?: bool|null, observacao?: string|null}  $dados */
    public function executar(array $dados, ?Ocorrencia $ocorrencia = null): Ocorrencia
    {
        $ocorrencia ??= new Ocorrencia;
        $inicio = Carbon::parse($dados['inicio'])->startOfDay();
        $fim = Carbon::parse($dados['fim'])->startOfDay();

        if ($fim->lt($inicio)) {
            throw ValidationException::withMessages(['fim' => 'O fim não pode ser antes do início.']);
        }

        $this->recusarSeFechada($inicio, $fim, 'lançada ou alterada');

        if ($ocorrencia->exists) {
            $this->recusarSeFechada($ocorrencia->inicio, $ocorrencia->fim, 'lançada ou alterada');
        }

        $ocorrencia->fill([
            'colaborador_id' => $ocorrencia->exists ? $ocorrencia->colaborador_id : $dados['colaborador_id'],
            'tipo' => TipoOcorrencia::from($dados['tipo']),
            'inicio' => $inicio,
            'fim' => $fim,
            'abona' => (bool) ($dados['abona'] ?? false),
            'observacao' => ($dados['observacao'] ?? null) === null ? null : trim($dados['observacao']),
        ])->save();

        return $ocorrencia;
    }

    public function excluir(Ocorrencia $ocorrencia): void
    {
        $this->recusarSeFechada($ocorrencia->inicio, $ocorrencia->fim, 'excluída');

        $ocorrencia->delete();
    }

    private function recusarSeFechada(Carbon $inicio, Carbon $fim, string $acao): void
    {
        for ($mes = $inicio->copy()->startOfMonth(); $mes->lte($fim); $mes->addMonth()) {
            if ($this->competencias->fechada($mes->copy()->max($inicio))) {
                throw ValidationException::withMessages(['inicio' => 'A competência de '.$mes->format('m/Y').' já está fechada. A ocorrência não pode ser '.$acao.' nesse mês.']);
            }
        }
    }
}

<?php

namespace App\Actions\Calendario;

use App\Enums\AbrangenciaFeriado;
use App\Models\Feriado;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Cria ou edita um feriado, com as regras de abrangência e de competência fechada. */
class SalvarFeriado
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    /** @param  array{data: string, descricao: string, abrangencia: string, obra_id?: int|string|null}  $dados */
    public function executar(array $dados, ?Feriado $feriado = null): Feriado
    {
        $feriado ??= new Feriado;
        $data = Carbon::parse($dados['data'])->startOfDay();
        $abrangencia = AbrangenciaFeriado::from($dados['abrangencia']);
        $obraId = $abrangencia === AbrangenciaFeriado::Nacional ? null : ($dados['obra_id'] ?? null);

        if ($abrangencia !== AbrangenciaFeriado::Nacional && ! $obraId) {
            throw ValidationException::withMessages(['obra_id' => 'Feriado '.mb_strtolower($abrangencia->rotulo()).' precisa da obra a que se aplica.']);
        }

        foreach (array_filter([$data, $feriado->exists ? $feriado->data : null]) as $dia) {
            if ($this->competencias->fechada($dia)) {
                throw ValidationException::withMessages(['data' => 'A competência de '.$dia->format('m/Y').' já está fechada. O feriado não pode ser criado nem alterado nesse mês.']);
            }
        }

        $repetido = Feriado::query()
            ->whereDate('data', $data)
            ->where('abrangencia', $abrangencia->value)
            ->when($obraId, fn ($q) => $q->where('obra_id', $obraId), fn ($q) => $q->whereNull('obra_id'))
            ->when($feriado->exists, fn ($q) => $q->whereKeyNot($feriado->id))
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages(['data' => 'Já existe um feriado '.mb_strtolower($abrangencia->rotulo()).' nessa data'.($obraId ? ' para essa obra' : '').'.']);
        }

        $feriado->fill([
            'data' => $data,
            'descricao' => trim($dados['descricao']),
            'abrangencia' => $abrangencia,
            'obra_id' => $obraId,
        ])->save();

        return $feriado;
    }

    public function excluir(Feriado $feriado): void
    {
        if ($this->competencias->fechada($feriado->data)) {
            throw ValidationException::withMessages(['data' => 'A competência de '.$feriado->data->format('m/Y').' já está fechada. O feriado não pode ser excluído.']);
        }

        $feriado->delete();
    }
}

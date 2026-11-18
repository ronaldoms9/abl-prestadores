<?php

namespace App\Actions\Ferias;

use App\Enums\AbrangenciaFeriado;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\Feriado;
use App\Models\FeriasSolicitacao;
use App\Models\Ocorrencia;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eventos do calendário de ausências em um intervalo (datas inclusive), no
 * formato do FullCalendar: `end` é exclusivo, por isso soma um dia.
 */
class EventosCalendario
{
    /** @return list<array<string, mixed>> */
    public function para(User $usuario, CarbonInterface $inicio, CarbonInterface $fim, ?int $obraId = null, ?int $equipeId = null): array
    {
        $colaboradores = fn (Builder $q) => $q
            ->visiveisPara($usuario)
            ->when($obraId, fn ($w) => $w->where('obra_id', $obraId))
            ->when($equipeId, fn ($w) => $w->whereIn('colaboradores.id', ColaboradorEquipe::query()
                ->where('equipe_id', $equipeId)
                ->whereDate('inicio', '<=', $fim)
                ->where(fn ($v) => $v->whereNull('fim')->orWhereDate('fim', '>=', $inicio))
                ->select('colaborador_id')));

        $eventos = [];

        FeriasSolicitacao::query()
            ->with('colaborador.pessoaFisica')
            ->whereIn('status', [StatusSolicitacaoFerias::Pendente->value, StatusSolicitacaoFerias::Aprovada->value])
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->whereHas('colaborador', $colaboradores)
            ->get()
            ->each(function (FeriasSolicitacao $s) use (&$eventos) {
                $pendente = $s->status === StatusSolicitacaoFerias::Pendente;
                $eventos[] = $this->evento(
                    'ferias-'.$s->id,
                    $s->colaborador->pessoaFisica->nome.' — férias'.($pendente ? ' (pendente)' : ''),
                    $s->inicio, $s->fim,
                    $pendente ? 'ev-ferias-pendente' : 'ev-ferias',
                );
            });

        Ocorrencia::query()
            ->with('colaborador.pessoaFisica')
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->whereHas('colaborador', $colaboradores)
            ->get()
            ->each(function (Ocorrencia $o) use (&$eventos) {
                $eventos[] = $this->evento(
                    'ocorrencia-'.$o->id,
                    $o->colaborador->pessoaFisica->nome.' — '.mb_strtolower($o->tipo->rotulo()).($o->abona ? ' abonada' : ''),
                    $o->inicio, $o->fim,
                    'ev-'.$o->tipo->value,
                );
            });

        Feriado::query()
            ->whereDate('data', '>=', $inicio)->whereDate('data', '<=', $fim)
            ->where(fn ($q) => $q->where('abrangencia', AbrangenciaFeriado::Nacional->value)
                ->orWhere(fn ($w) => $w->when($obraId, fn ($v) => $v->where('obra_id', $obraId))))
            ->get()
            ->each(function (Feriado $f) use (&$eventos) {
                $eventos[] = $this->evento('feriado-'.$f->id, $f->descricao, $f->data, $f->data, 'ev-feriado');
            });

        return $eventos;
    }

    /** @return array<string, mixed> */
    private function evento(string $id, string $titulo, CarbonInterface $inicio, CarbonInterface $fim, string $classe): array
    {
        return [
            'id' => $id,
            'title' => $titulo,
            'start' => $inicio->toDateString(),
            'end' => $fim->copy()->addDay()->toDateString(),
            'allDay' => true,
            'classNames' => [$classe],
        ];
    }
}

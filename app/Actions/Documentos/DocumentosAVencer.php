<?php

namespace App\Actions\Documentos;

use App\Enums\StatusColaborador;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Documentos vencidos e a vencer, para os alertas e o indicador do colaborador.
 *
 * Só conta o documento mais recente de cada tipo para cada dono: ao anexar a
 * renovação, o antigo vencido deixa de aparecer. Ignora donos fora de uso
 * (colaborador desligado, PF ou PJ inativa).
 */
class DocumentosAVencer
{
    /**
     * @return array{vencidos: Collection<int, Documento>, a_vencer: Collection<int, Documento>}
     */
    public function consultar(int $dias, CarbonInterface $hoje): array
    {
        $documentos = Documento::query()
            ->whereNotNull('validade')
            ->with(['tipoDocumento', 'documentavel' => function (MorphTo $morph) {
                $morph->morphWith([Colaborador::class => ['pessoaFisica']]);
            }])
            ->get();

        return $this->classificar($this->maisRecentes($documentos)->filter(fn (Documento $d) => $this->emUso($d)), $dias, $hoje);
    }

    /**
     * Vencidos do colaborador: os dele, os da PF e os da PJ.
     *
     * @return Collection<int, Documento>
     */
    public function vencidosDoColaborador(Colaborador $colaborador, CarbonInterface $hoje): Collection
    {
        $documentos = Documento::query()
            ->whereNotNull('validade')
            ->where(function ($q) use ($colaborador) {
                $q->where(fn ($w) => $w->where('documentavel_type', 'colaborador')->where('documentavel_id', $colaborador->id))
                    ->orWhere(fn ($w) => $w->where('documentavel_type', 'pessoa-fisica')->where('documentavel_id', $colaborador->pessoa_fisica_id))
                    ->orWhere(fn ($w) => $w->where('documentavel_type', 'pessoa-juridica')->where('documentavel_id', $colaborador->pessoa_juridica_id));
            })
            ->with('tipoDocumento')
            ->get();

        return $this->classificar($this->maisRecentes($documentos), 0, $hoje)['vencidos'];
    }

    /** @return Collection<int, Documento> */
    private function maisRecentes(Collection $documentos): Collection
    {
        return $documentos
            ->groupBy(fn (Documento $d) => $d->documentavel_type.':'.$d->documentavel_id.':'.($d->documento_tipo_id ?? $d->tipo))
            ->map(fn (Collection $grupo) => $grupo->sortByDesc(fn (Documento $d) => [$d->validade->timestamp, $d->id])->first())
            ->values();
    }

    private function emUso(Documento $documento): bool
    {
        $dono = $documento->documentavel;

        return match (true) {
            $dono instanceof Colaborador => $dono->status !== StatusColaborador::Desligado,
            $dono instanceof PessoaFisica, $dono instanceof PessoaJuridica => (bool) $dono->ativo,
            default => false,
        };
    }

    /** @return array{vencidos: Collection<int, Documento>, a_vencer: Collection<int, Documento>} */
    private function classificar(Collection $documentos, int $dias, CarbonInterface $hoje): array
    {
        $porSituacao = $documentos->groupBy(fn (Documento $d) => $d->situacaoEm($hoje, $dias));

        return [
            'vencidos' => ($porSituacao[Documento::VENCIDO] ?? collect())->sortBy('validade')->values(),
            'a_vencer' => ($porSituacao[Documento::A_VENCER] ?? collect())->sortBy('validade')->values(),
        ];
    }
}

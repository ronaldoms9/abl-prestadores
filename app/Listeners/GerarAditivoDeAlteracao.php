<?php

namespace App\Listeners;

use App\Actions\Contratos\CriarRascunhoDeAditivo;
use App\Enums\MotivoAditivo;
use App\Events\BeneficiosDoColaboradorAlterados;
use App\Events\CargoDoColaboradorAlterado;
use App\Models\Cargo;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Rascunho de aditivo para mudança de cargo e alteração de benefícios de quem já tem contrato (E3, fatia 9). */
class GerarAditivoDeAlteracao implements ShouldQueue
{
    public function __construct(private readonly CriarRascunhoDeAditivo $criar)
    {
    }

    public function handle(CargoDoColaboradorAlterado|BeneficiosDoColaboradorAlterados $evento): void
    {
        if ($evento instanceof CargoDoColaboradorAlterado) {
            $nomes = Cargo::query()->whereIn('id', array_filter([$evento->cargoAnteriorId, $evento->cargoNovoId]))->pluck('nome', 'id');

            $this->criar->executar(
                $evento->colaborador,
                MotivoAditivo::MudancaCargo,
                Carbon::today(),
                ['cargo_anterior' => $nomes[$evento->cargoAnteriorId] ?? null, 'cargo_novo' => $nomes[$evento->cargoNovoId] ?? null],
                'cargo:'.$evento->colaborador->id.':'.($evento->cargoAnteriorId ?? 0).'>'.($evento->cargoNovoId ?? 0).':'.Carbon::today()->toDateString(),
            );

            return;
        }

        $this->criar->executar(
            $evento->colaborador,
            MotivoAditivo::Beneficios,
            Carbon::parse($evento->inicio),
            ['beneficios_alteracao' => $evento->descricao],
            'beneficios:'.$evento->colaborador->id.':'.md5($evento->descricao.$evento->inicio),
        );
    }
}

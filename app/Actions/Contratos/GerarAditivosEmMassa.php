<?php

namespace App\Actions\Contratos;

use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Contrato;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gera vários rascunhos de aditivo de uma vez. Cada um é gerado na sua própria
 * transação: o que falta dado (ou tem outro problema) fica de fora, com a
 * mensagem, e não impede os demais.
 */
class GerarAditivosEmMassa
{
    public function __construct(private readonly GerarContrato $gerar)
    {
    }

    /**
     * @param  Collection<int, Contrato>  $rascunhos
     * @return array{gerados: int, falhas: list<array{id: int, colaborador: string, mensagem: string}>}
     */
    public function executar(Collection $rascunhos): array
    {
        $gerados = 0;
        $falhas = [];

        foreach ($rascunhos as $rascunho) {
            if ($rascunho->tipo !== TipoContrato::Aditivo || $rascunho->status !== StatusContrato::Rascunho) {
                continue;
            }

            try {
                $this->gerar->executar($rascunho);
                $gerados++;
            } catch (ValidationException $e) {
                $falhas[] = [
                    'id' => $rascunho->id,
                    'colaborador' => $rascunho->colaborador->pessoaFisica->nome,
                    'mensagem' => implode(' ', $e->validator->errors()->all()),
                ];
            }
        }

        return ['gerados' => $gerados, 'falhas' => $falhas];
    }
}

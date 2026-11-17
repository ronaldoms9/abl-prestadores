<?php

namespace App\Actions\Contratos;

use App\Actions\Beneficios\BeneficioVigente;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\Remuneracao;
use App\Support\Contratos\ContratoVariaveis;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;

/**
 * Junta os dados do cadastro para preencher as variáveis do modelo. A
 * remuneração e os benefícios são os vigentes na data de referência: o início
 * da vigência do contrato, ou hoje quando ele não tem.
 */
class MontarValoresContrato
{
    public function __construct(private readonly BeneficioVigente $beneficios)
    {
    }

    /**
     * @param  array<string, mixed>  $dados  Próprios de aditivo e distrato.
     * @return array<string, string|HtmlString>
     */
    public function executar(Colaborador $colaborador, ?Contrato $contrato = null, array $dados = [], ?CarbonInterface $hoje = null): array
    {
        $hoje ??= now();
        $referencia = ($contrato?->vigencia_inicio ?? $hoje)->copy()->startOfDay();

        $colaborador = Colaborador::query()
            ->with(['pessoaFisica', 'pessoaJuridica.responsavelAtual.pessoaFisica', 'cargo', 'empresa', 'obra'])
            ->findOrFail($colaborador->id);

        $contrato?->loadMissing('pai');

        /** @var Remuneracao|null $remuneracao */
        $remuneracao = Remuneracao::query()->where('colaborador_id', $colaborador->id)->vigentesEm($referencia)->first();

        return ContratoVariaveis::valores(
            $colaborador,
            $remuneracao === null ? null : Dinheiro::centavos($remuneracao->valor_mensal),
            $remuneracao?->vigencia_inicio,
            $this->beneficios->listaEm($colaborador, $referencia),
            $contrato,
            $dados,
            $hoje,
        );
    }
}

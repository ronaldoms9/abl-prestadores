<?php

namespace App\Actions\Contratos;

use App\Enums\StatusContrato;
use App\Jobs\GerarPdfContrato;
use App\Models\Contrato;
use App\Support\Auditoria;
use App\Support\Contratos\RenderizadorContrato;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Transforma o rascunho em contrato gerado: numera, preenche o modelo com os
 * dados do cadastro e grava o texto. Se faltar dado para alguma variável do
 * modelo, recusa e diz quais (o contrato não sai com "sem valor"). O PDF é
 * gerado depois, em fila. Depois de gerado o contrato é imutável.
 */
class GerarContrato
{
    public function __construct(
        private readonly MontarValoresContrato $valores,
        private readonly RenderizadorContrato $renderizador,
        private readonly ProximoNumeroContrato $numeracao,
    ) {
    }

    /** Resultado do que sairia, sem gravar nada: para a pré-visualização. */
    public function previa(Contrato $contrato, ?CarbonInterface $hoje = null): \App\Support\Contratos\ResultadoRenderizacao
    {
        $contrato->loadMissing(['modelo', 'colaborador']);

        // O número só sai ao gerar; na prévia ele aparece como aviso, não como dado faltando.
        $provisorio = clone $contrato;
        $provisorio->setAttribute('numero', '[número definido ao gerar]');

        $valores = $this->valores->executar($contrato->colaborador, $provisorio, $contrato->dados ?? [], $hoje);

        return $this->renderizador->renderizar($contrato->modelo->corpo_html, $valores);
    }

    public function executar(Contrato $contrato, ?CarbonInterface $hoje = null): Contrato
    {
        $hoje ??= now();

        $gerado = DB::transaction(function () use ($contrato, $hoje) {
            $contrato = Contrato::query()->with(['modelo', 'colaborador', 'pai'])->lockForUpdate()->findOrFail($contrato->id);

            if ($contrato->status !== StatusContrato::Rascunho) {
                throw ValidationException::withMessages(['contrato' => 'Este contrato já foi gerado.']);
            }

            $numero = $this->numeracao->para($contrato->tipo, $contrato->pai, $hoje);
            $contrato->numero = $numero['numero'];

            $resultado = $this->renderizador->renderizar(
                $contrato->modelo->corpo_html,
                $this->valores->executar($contrato->colaborador, $contrato, $contrato->dados ?? [], $hoje),
            );

            if (! $resultado->completo()) {
                throw ValidationException::withMessages(['contrato' => 'Faltam dados no cadastro para preencher o modelo: '.implode(', ', array_map(fn ($v) => '{{'.$v.'}}', $resultado->faltantes)).'.']);
            }

            $contrato->fill([
                'ano' => $numero['ano'],
                'sequencia' => $numero['sequencia'],
                'conteudo_renderizado' => $resultado->html,
                'status' => StatusContrato::Gerado,
                'gerado_em' => now(),
            ])->save();

            Auditoria::registrar('contrato-gerado', $contrato, ['numero' => $contrato->numero, 'tipo' => $contrato->tipo->value, 'colaborador_id' => $contrato->colaborador_id]);

            DB::afterCommit(function () use ($contrato) {
                // Sem fila assíncrona (ou sem a biblioteca de PDF), o contrato já está gerado:
                // a falha vai para o log e a tela oferece gerar o PDF de novo.
                try {
                    GerarPdfContrato::dispatch($contrato->id);
                } catch (Throwable $e) {
                    report($e);
                }
            });

            return $contrato;
        });

        return $gerado;
    }
}

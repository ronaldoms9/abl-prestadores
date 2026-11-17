<?php

namespace App\Actions\Contratos;

use App\Enums\MotivoAditivo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria sozinho o rascunho de aditivo quando algo muda para um colaborador que
 * já tem contrato em vigor (reajuste, cargo, benefícios). Não gera número nem
 * PDF: o RH confere e gera na tela de aditivos pendentes. Silencioso quando
 * não cabe aditivo (sem contrato gerado ou assinado, colaborador desligado, sem
 * modelo de aditivo): devolve o motivo em vez de lançar erro, para o evento que
 * disparou não quebrar. A mesma alteração não gera dois rascunhos (chave).
 */
class CriarRascunhoDeAditivo
{
    public const SEM_CONTRATO = 'sem_contrato';

    public const DESLIGADO = 'desligado';

    public const SEM_MODELO = 'sem_modelo';

    public const DUPLICADO = 'duplicado';

    public function __construct(private readonly CriarContrato $criar)
    {
    }

    /**
     * @param  array<string, mixed>  $dados  Vão para as variáveis do aditivo.
     * @param  string|null  $chave  Identifica a alteração (ex.: "reajuste:7"); repetir a chave não duplica.
     * @return array{rascunho: ?Contrato, motivo: ?string}
     */
    public function executar(Colaborador $colaborador, MotivoAditivo $motivo, CarbonInterface $inicio, array $dados = [], ?string $chave = null): array
    {
        if ($colaborador->status === StatusColaborador::Desligado) {
            return ['rascunho' => null, 'motivo' => self::DESLIGADO];
        }

        $pai = Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->latest('id')
            ->first();

        if ($pai === null) {
            return ['rascunho' => null, 'motivo' => self::SEM_CONTRATO];
        }

        $modelo = ContratoModelo::aditivoPara($motivo);
        if ($modelo === null) {
            return ['rascunho' => null, 'motivo' => self::SEM_MODELO];
        }

        return DB::transaction(function () use ($colaborador, $modelo, $pai, $motivo, $inicio, $dados, $chave) {
            if ($chave !== null) {
                $dados['chave'] = $chave;

                if (Contrato::query()->where('contrato_pai_id', $pai->id)->where('tipo', TipoContrato::Aditivo->value)->where('dados->chave', $chave)->lockForUpdate()->exists()) {
                    return ['rascunho' => null, 'motivo' => self::DUPLICADO];
                }
            }

            try {
                $rascunho = $this->criar->executar($colaborador, $modelo, $inicio, null, $pai, $motivo, $dados);
            } catch (ValidationException) {
                return ['rascunho' => null, 'motivo' => self::SEM_CONTRATO];
            }

            return ['rascunho' => $rascunho, 'motivo' => null];
        });
    }
}

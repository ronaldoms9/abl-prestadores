<?php

namespace App\Actions\Qualificacoes;

use App\Models\Colaborador;
use App\Models\ColaboradorQualificacao;
use App\Models\QualificacaoTipo;
use App\Support\Auditoria;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registra uma qualificação do colaborador. A validade é calculada a partir da
 * conclusão e da validade em meses do tipo (sem meses, não vence). O
 * certificado, opcional, vai para o disco privado de documentos.
 */
class RegistrarQualificacao
{
    public function executar(Colaborador $colaborador, QualificacaoTipo $tipo, CarbonInterface $conclusao, ?string $instituicao = null, ?UploadedFile $certificado = null): ColaboradorQualificacao
    {
        if (! $tipo->ativo) {
            throw ValidationException::withMessages(['qualificacao_tipo_id' => 'Este tipo de qualificação está inativo.']);
        }

        if ($conclusao->copy()->startOfDay()->gt(now()->startOfDay())) {
            throw ValidationException::withMessages(['data_conclusao' => 'A data de conclusão não pode ser futura.']);
        }

        return DB::transaction(function () use ($colaborador, $tipo, $conclusao, $instituicao, $certificado) {
            $qualificacao = ColaboradorQualificacao::create([
                'colaborador_id' => $colaborador->id,
                'qualificacao_tipo_id' => $tipo->id,
                'data_conclusao' => $conclusao->copy()->startOfDay(),
                'data_validade' => self::validade($conclusao, $tipo),
                'instituicao' => $instituicao !== null && trim($instituicao) !== '' ? trim($instituicao) : null,
            ]);

            if ($certificado !== null) {
                $caminho = $certificado->storeAs(
                    "qualificacoes/{$tipo->id}/{$qualificacao->id}",
                    Str::uuid().'.'.Str::lower($certificado->getClientOriginalExtension() ?: $certificado->extension()),
                    config('cadastros.documentos.disco'),
                );

                if ($caminho === false) {
                    throw new \RuntimeException('Não foi possível gravar o certificado.');
                }

                $qualificacao->update(['certificado_path' => $caminho, 'certificado_nome' => Str::limit($certificado->getClientOriginalName(), 200, '')]);
            }

            Auditoria::registrar('qualificacao-registrada', $colaborador, [
                'qualificacao' => $tipo->nome,
                'conclusao' => $qualificacao->data_conclusao->toDateString(),
                'validade' => $qualificacao->data_validade?->toDateString(),
            ]);

            return $qualificacao;
        });
    }

    /** Conclusão mais a validade do tipo em meses (sem estourar o fim do mês); null quando o tipo não vence. */
    public static function validade(CarbonInterface $conclusao, QualificacaoTipo $tipo): ?CarbonInterface
    {
        return $tipo->validade_meses ? $conclusao->copy()->startOfDay()->addMonthsNoOverflow($tipo->validade_meses) : null;
    }

    public function excluir(ColaboradorQualificacao $qualificacao): void
    {
        $colaborador = $qualificacao->colaborador;
        $nome = $qualificacao->tipo->nome;
        $qualificacao->delete();

        Auditoria::registrar('qualificacao-excluida', $colaborador, ['qualificacao' => $nome]);
    }
}

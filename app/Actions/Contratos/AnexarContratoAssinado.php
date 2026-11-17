<?php

namespace App\Actions\Contratos;

use App\Enums\StatusContrato;
use App\Models\Contrato;
use App\Support\Auditoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Anexa o contrato assinado (PDF ou imagem) a um contrato gerado e o marca como assinado. */
class AnexarContratoAssinado
{
    public function executar(Contrato $contrato, UploadedFile $arquivo): Contrato
    {
        return DB::transaction(function () use ($contrato, $arquivo) {
            $contrato = Contrato::query()->lockForUpdate()->findOrFail($contrato->id);

            if ($contrato->status !== StatusContrato::Gerado) {
                throw ValidationException::withMessages(['arquivo' => $contrato->status === StatusContrato::Assinado
                    ? 'Este contrato já tem o arquivo assinado.'
                    : 'Só um contrato gerado pode receber o arquivo assinado.']);
            }

            $caminho = $arquivo->storeAs(
                "contratos/{$contrato->ano}/{$contrato->id}",
                'assinado-'.Str::uuid().'.'.Str::lower($arquivo->getClientOriginalExtension() ?: $arquivo->extension()),
                config('cadastros.contratos.disco'),
            );

            if ($caminho === false) {
                throw new \RuntimeException('Não foi possível gravar o arquivo assinado.');
            }

            $contrato->update(['assinado_path' => $caminho, 'assinado_em' => now(), 'status' => StatusContrato::Assinado]);

            Auditoria::registrar('contrato-assinado', $contrato, ['numero' => $contrato->numero]);

            return $contrato;
        });
    }

    public static function arquivoExiste(?string $caminho): bool
    {
        return $caminho !== null && Storage::disk(config('cadastros.contratos.disco'))->exists($caminho);
    }
}

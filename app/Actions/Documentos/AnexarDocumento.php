<?php

namespace App\Actions\Documentos;

use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Support\Auditoria;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anexa um documento a uma PF, PJ ou colaborador. O arquivo vai para o disco
 * privado em documentos/{tipo}/{id}/ e só sai por rota autorizada. A validade
 * é obrigatória quando o tipo exige. Falha ao gravar o arquivo desfaz o registro.
 */
class AnexarDocumento
{
    public function executar(Model $dono, DocumentoTipo $tipo, UploadedFile $arquivo, ?CarbonInterface $validade = null, ?string $observacao = null): Documento
    {
        $apelido = array_search($dono::class, Relation::morphMap(), true);

        if ($apelido === false || ! $tipo->ativo || ! $tipo->aplica_a->aceita($apelido)) {
            throw ValidationException::withMessages(['documento_tipo_id' => 'Este tipo de documento não serve para este cadastro.']);
        }

        if ($tipo->exige_validade && $validade === null) {
            throw ValidationException::withMessages(['validade' => "O tipo \"{$tipo->nome}\" exige a data de validade."]);
        }

        return DB::transaction(function () use ($dono, $tipo, $arquivo, $validade, $observacao, $apelido) {
            $documento = new Documento([
                'documento_tipo_id' => $tipo->id,
                'tipo' => $tipo->nome,
                'validade' => $validade?->toDateString(),
                'observacao' => $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null,
                'arquivo_nome' => Str::limit($arquivo->getClientOriginalName(), 200, ''),
                'arquivo_mime' => $arquivo->getClientMimeType(),
                'arquivo_tamanho' => $arquivo->getSize(),
            ]);
            $documento->documentavel()->associate($dono);
            $documento->save();

            $caminho = $arquivo->storeAs(
                "documentos/{$tipo->id}/{$documento->id}",
                Str::uuid().'.'.Str::lower($arquivo->getClientOriginalExtension() ?: $arquivo->extension()),
                config('cadastros.documentos.disco'),
            );

            if ($caminho === false) {
                throw new \RuntimeException('Não foi possível gravar o arquivo do documento.');
            }

            $documento->update(['arquivo_path' => $caminho]);

            Auditoria::registrar('documento-anexado', $dono, ['tipo' => $tipo->nome, 'validade' => $documento->validade?->toDateString()]);

            return $documento;
        });
    }

    /** Remove o registro (exclusão lógica); o arquivo fica guardado para a retenção legal. */
    public function excluir(Documento $documento): void
    {
        $dono = $documento->documentavel;
        $documento->delete();

        Auditoria::registrar('documento-excluido', $dono, ['tipo' => $documento->tipo]);
    }

    public static function arquivoExiste(Documento $documento): bool
    {
        return $documento->arquivo_path !== null && Storage::disk(config('cadastros.documentos.disco'))->exists($documento->arquivo_path);
    }
}

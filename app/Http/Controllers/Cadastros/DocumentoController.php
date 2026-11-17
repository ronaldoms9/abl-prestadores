<?php

namespace App\Http\Controllers\Cadastros;

use App\Actions\Documentos\AnexarDocumento;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Anexar, baixar e excluir documentos de PF, PJ e colaborador (E2, fatia 5).
 * O arquivo está em disco privado: só sai por aqui, e o documento de um
 * colaborador só para quem pode ver aquele colaborador (equipes que gere, D7).
 */
class DocumentoController extends Controller
{
    use ValidaFormulario;

    public function store(Request $request, AnexarDocumento $anexar, string $apelido, $id)
    {
        $dono = $this->dono($apelido, (int) $id);

        $validos = $this->validarFormulario($request, $request->all() + ['arquivo' => $request->file('arquivo')], [
            'documento_tipo_id' => ['required', Rule::exists('documento_tipos', 'id')->whereNull('deleted_at')],
            'arquivo' => ['required', 'file', 'max:'.config('cadastros.documentos.tamanho_maximo_kb'), 'mimes:'.implode(',', config('cadastros.documentos.extensoes'))],
            'validade' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], ['documento_tipo_id' => 'tipo de documento', 'arquivo' => 'arquivo', 'validade' => 'validade', 'observacao' => 'observação']);

        try {
            $anexar->executar(
                $dono,
                DocumentoTipo::findOrFail($validos['documento_tipo_id']),
                $request->file('arquivo'),
                isset($validos['validade']) ? Carbon::parse($validos['validade']) : null,
                $validos['observacao'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'Documento anexado.', 'data' => []]);
    }

    public function baixar($id)
    {
        $documento = Documento::with('documentavel')->findOrFail($id);
        $this->autorizarDono($documento);

        $disco = Storage::disk(config('cadastros.documentos.disco'));
        abort_unless($documento->arquivo_path !== null && $disco->exists($documento->arquivo_path), 404, 'Arquivo não encontrado.');

        return $disco->download($documento->arquivo_path, $documento->arquivo_nome ?? basename($documento->arquivo_path));
    }

    public function excluir(AnexarDocumento $anexar, $id)
    {
        $documento = Documento::with('documentavel')->findOrFail($id);
        $this->autorizarDono($documento);

        $anexar->excluir($documento);

        return response()->json(['success' => true, 'message' => 'Documento excluído.', 'data' => null], 201);
    }

    private function dono(string $apelido, int $id): Model
    {
        return match ($apelido) {
            'pessoa-fisica' => PessoaFisica::findOrFail($id),
            'pessoa-juridica' => PessoaJuridica::findOrFail($id),
            'colaborador' => Colaborador::visiveisPara(auth()->user())->findOrFail($id),
        };
    }

    private function autorizarDono(Documento $documento): void
    {
        if ($documento->documentavel instanceof Colaborador) {
            abort_unless(Colaborador::visiveisPara(auth()->user())->whereKey($documento->documentavel_id)->exists(), 404);
        }
    }
}

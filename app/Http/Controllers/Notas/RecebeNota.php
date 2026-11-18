<?php

namespace App\Http\Controllers\Notas;

use App\Actions\Notas\EnviarNota;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Services\NotaFiscal\LeitorXmlNfse;
use App\Services\NotaFiscal\XmlNaoLido;
use App\Support\Dinheiro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** O que o portal e a tela interna de notas têm em comum: prévia do XML, envio e download. */
trait RecebeNota
{
    /** Lê o XML enviado e devolve o que foi lido, para o colaborador conferir antes de confirmar. */
    protected function previaDoXml(Request $request)
    {
        $request->validate(['xml' => ['required', 'file', 'max:2048']], [], ['xml' => 'arquivo XML']);

        try {
            $n = app(LeitorXmlNfse::class)->ler((string) file_get_contents($request->file('xml')->getRealPath()));
        } catch (XmlNaoLido $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => 'XML lido.', 'data' => [
            'layout' => $n->layout,
            'numero' => $n->numero,
            'serie' => $n->serie,
            'emissao' => $n->emissao?->format('d/m/Y'),
            'cnpj_emissor' => $n->cnpjEmissor,
            'cnpj_tomador' => $n->cnpjTomador,
            'valor_bruto' => $n->valorBruto === null ? null : Dinheiro::formatar($n->valorBruto),
            'retencoes' => $n->totalRetencoes() > 0 ? Dinheiro::formatar($n->totalRetencoes()) : null,
        ]]);
    }

    protected function enviarNota(Request $request, EnviarNota $enviar, Fechamento $fechamento)
    {
        $dados = $request->validate([
            'xml' => ['nullable', 'file', 'max:2048'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'numero' => ['nullable', 'string', 'max:30'],
            'serie' => ['nullable', 'string', 'max:10'],
            'chave_acesso' => ['nullable', 'string', 'max:70'],
            'data_emissao' => ['nullable', 'date'],
            'cnpj_emissor' => ['nullable', 'string', 'max:20'],
            'cnpj_tomador' => ['nullable', 'string', 'max:20'],
            'valor_bruto' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'retencoes' => ['nullable', 'array'],
            'retencoes.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ], [], ['xml' => 'arquivo XML', 'pdf' => 'arquivo PDF', 'data_emissao' => 'data de emissão', 'valor_bruto' => 'valor bruto']);

        try {
            $nota = $enviar->executar($fechamento, $request->file('xml'), $request->file('pdf'), $dados, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        $mensagem = $nota->status->value === 'divergente'
            ? 'Nota enviada, mas a conferência achou divergências: '.implode(' ', array_column($nota->divergencias, 'mensagem'))
            : 'Nota enviada e conferida.';

        return response()->json(['success' => true, 'message' => $mensagem, 'data' => ['id' => $nota->id, 'status' => $nota->status->value]]);
    }

    protected function baixar(NotaFiscal $nota, string $tipo): StreamedResponse
    {
        $caminho = match ($tipo) { 'xml' => $nota->xml_path, 'pdf' => $nota->pdf_path, default => null };
        abort_if($caminho === null, 404);

        $disco = Storage::disk(config('cadastros.notas.disco'));
        abort_unless($disco->exists($caminho), 404);

        return $disco->download($caminho, 'nota-'.$nota->competencia_id.'-'.$nota->colaborador_id.'.'.$tipo);
    }
}

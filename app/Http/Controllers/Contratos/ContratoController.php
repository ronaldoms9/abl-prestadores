<?php

namespace App\Http\Controllers\Contratos;

use App\Actions\Contratos\AnexarContratoAssinado;
use App\Actions\Contratos\CancelarRascunho;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Actions\Contratos\RenovarContrato;
use App\Enums\MotivoAditivo;
use App\Enums\StatusContrato;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Jobs\GerarPdfContrato;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Contrato, aditivo e distrato do colaborador: rascunho, pré-visualização,
 * geração, arquivo assinado e downloads (E3, fatia 3). Tudo respeita a
 * visibilidade do colaborador (o gestor só vê as equipes que gere, D7).
 */
class ContratoController extends Controller
{
    use ValidaFormulario;

    public function store(Request $request, CriarContrato $criar, $id)
    {
        $colaborador = Colaborador::visiveisPara(auth()->user())->findOrFail($id);

        $dados = $request->all();
        foreach (['vigencia_inicio', 'vigencia_fim', 'contrato_pai_id', 'motivo'] as $campo) {
            $dados[$campo] = ($dados[$campo] ?? '') === '' ? null : $dados[$campo];
        }

        $validos = $this->validarFormulario($request, $dados, [
            'contrato_modelo_id' => ['required', Rule::exists('contrato_modelos', 'id')->whereNull('deleted_at')],
            'vigencia_inicio' => ['nullable', 'date'],
            'vigencia_fim' => ['nullable', 'date'],
            'contrato_pai_id' => ['nullable', 'integer'],
            'motivo' => ['nullable', Rule::enum(MotivoAditivo::class)],
        ], ['contrato_modelo_id' => 'modelo', 'vigencia_inicio' => 'início da vigência', 'vigencia_fim' => 'fim da vigência', 'contrato_pai_id' => 'contrato', 'motivo' => 'motivo do aditivo']);

        try {
            $contrato = $criar->executar(
                $colaborador,
                ContratoModelo::findOrFail($validos['contrato_modelo_id']),
                isset($validos['vigencia_inicio']) ? Carbon::parse($validos['vigencia_inicio']) : null,
                isset($validos['vigencia_fim']) ? Carbon::parse($validos['vigencia_fim']) : null,
                isset($validos['contrato_pai_id']) ? Contrato::where('colaborador_id', $colaborador->id)->find($validos['contrato_pai_id']) : null,
                isset($validos['motivo']) ? MotivoAditivo::from($validos['motivo']) : null,
            );
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Rascunho criado. Confira a pré-visualização e gere o documento.', 'data' => ['id' => $contrato->id]]);
    }

    /** Renovar: cria o rascunho do aditivo de prorrogação até a nova data. */
    public function renovar(Request $request, RenovarContrato $renovar, $id)
    {
        $contrato = $this->contrato($id, ['colaborador']);

        $validos = $this->validarFormulario($request, $request->all(), ['vigencia_fim' => ['required', 'date']], ['vigencia_fim' => 'nova data de fim']);

        try {
            $aditivo = $renovar->executar($contrato, Carbon::parse($validos['vigencia_fim']));
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Rascunho do aditivo de prorrogação criado. Confira e gere o documento.', 'data' => ['id' => $aditivo->id]]);
    }

    /** Mostra como o documento ficaria, sem gravar, e avisa o que falta no cadastro. */
    public function previa(GerarContrato $gerar, $id)
    {
        $contrato = $this->contrato($id, ['modelo', 'colaborador.pessoaFisica']);

        return view('contratos.previa', [
            'contrato' => $contrato,
            'resultado' => $contrato->status === StatusContrato::Rascunho ? $gerar->previa($contrato) : null,
        ]);
    }

    public function gerar(GerarContrato $gerar, $id)
    {
        $contrato = $this->contrato($id);

        try {
            $gerado = $gerar->executar($contrato);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => $gerado->tipo->rotulo().' '.$gerado->numero.' gerado. O PDF fica pronto em instantes.', 'data' => ['id' => $gerado->id]]);
    }

    public function cancelar(CancelarRascunho $cancelar, $id)
    {
        $contrato = $this->contrato($id);

        try {
            $cancelar->executar($contrato);
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Rascunho cancelado.', 'data' => null], 201);
    }

    public function assinado(Request $request, AnexarContratoAssinado $anexar, $id)
    {
        $contrato = $this->contrato($id);

        $this->validarFormulario($request, ['arquivo' => $request->file('arquivo')], [
            'arquivo' => ['required', 'file', 'max:'.config('cadastros.contratos.tamanho_maximo_kb'), 'mimes:'.implode(',', config('cadastros.contratos.extensoes_assinado'))],
        ], ['arquivo' => 'arquivo assinado']);

        try {
            $anexar->executar($contrato, $request->file('arquivo'));
        } catch (ValidationException $e) {
            return $this->erro($e);
        }

        return response()->json(['success' => true, 'message' => 'Arquivo assinado anexado.', 'data' => []]);
    }

    public function pdf($id)
    {
        $contrato = $this->contrato($id);

        return $this->baixar($contrato, $contrato->pdf_path, $this->nomeDoArquivo($contrato, 'pdf'), 'O PDF ainda não foi gerado. Tente de novo em instantes ou peça para gerar novamente.');
    }

    public function baixarAssinado($id)
    {
        $contrato = $this->contrato($id);

        $extensao = pathinfo((string) $contrato->assinado_path, PATHINFO_EXTENSION) ?: 'pdf';

        return $this->baixar($contrato, $contrato->assinado_path, $this->nomeDoArquivo($contrato, $extensao, 'assinado'), 'Este contrato não tem arquivo assinado.');
    }

    public function regerarPdf($id)
    {
        $contrato = $this->contrato($id);

        if ($contrato->status === StatusContrato::Rascunho) {
            return response()->json(['success' => false, 'message' => 'Gere o documento antes do PDF.', 'data' => null], 422);
        }

        try {
            GerarPdfContrato::dispatch($contrato->id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Não foi possível gerar o PDF agora: '.$e->getMessage(), 'data' => null], 500);
        }

        return response()->json(['success' => true, 'message' => 'PDF solicitado. Atualize a tela em instantes.', 'data' => []]);
    }

    /** @param  array<int, string>  $relacoes */
    private function contrato($id, array $relacoes = []): Contrato
    {
        $contrato = Contrato::query()->with($relacoes)->findOrFail($id);
        Colaborador::visiveisPara(auth()->user())->findOrFail($contrato->colaborador_id);

        return $contrato;
    }

    private function baixar(Contrato $contrato, ?string $caminho, string $nome, string $ausente)
    {
        $disco = Storage::disk(config('cadastros.contratos.disco'));
        abort_unless($caminho !== null && $disco->exists($caminho), 404, $ausente);

        return $disco->download($caminho, $nome);
    }

    private function nomeDoArquivo(Contrato $contrato, string $extensao, ?string $sufixo = null): string
    {
        return str_replace('/', '-', ($contrato->numero ?? 'contrato-'.$contrato->id)).($sufixo ? "-{$sufixo}" : '').'.'.$extensao;
    }

    private function erro(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

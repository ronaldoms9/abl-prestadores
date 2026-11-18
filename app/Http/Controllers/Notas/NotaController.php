<?php

namespace App\Http\Controllers\Notas;

use App\Actions\Notas\AprovarNota;
use App\Actions\Notas\EnviarNota;
use App\Actions\Notas\RecusarNota;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Jobs\GerarZipNotas;
use App\Models\Competencia;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Notas fiscais por competência: conferência, aprovação, recusa e download em lote (E5, fatias 4 e 5). */
class NotaController extends Controller
{
    use RecebeNota, ValidaFormulario;

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'competencia_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_map(fn (StatusNota $s) => $s->value, StatusNota::cases()))],
            'obra_id' => ['nullable', 'integer'],
            'busca' => ['nullable', 'string', 'max:100'],
        ]);

        $competencias = Competencia::query()->whereIn('status', ['aprovada', 'fechada'])->orderByDesc('ano_mes')->get();
        $competencia = isset($filtros['competencia_id']) ? $competencias->firstWhere('id', (int) $filtros['competencia_id']) : $competencias->first();

        $fechamentos = collect();
        if ($competencia !== null) {
            $fechamentos = Fechamento::query()
                ->with(['colaborador.pessoaFisica', 'colaborador.obra', 'nota'])
                ->where('competencia_id', $competencia->id)
                ->where('status', StatusFechamento::AguardandoNota->value)
                ->when($filtros['obra_id'] ?? null, fn ($q, $obra) => $q->whereHas('colaborador', fn ($c) => $c->naObra($obra)))
                ->when(trim($filtros['busca'] ?? '') !== '', fn ($q) => $q->whereHas('colaborador.pessoaFisica', fn ($p) => $p->where('nome', 'like', '%'.trim($filtros['busca']).'%')))
                ->get()
                ->filter(fn (Fechamento $f) => ! isset($filtros['status']) || ($f->nota?->status ?? StatusNota::Aguardando)->value === $filtros['status'])
                ->sortBy(fn (Fechamento $f) => $f->colaborador->pessoaFisica->nome)->values();
        }

        return view('notas.index', [
            'competencias' => $competencias,
            'competencia' => $competencia,
            'fechamentos' => $fechamentos,
            'filtros' => $filtros,
            'obras' => Obra::query()->ativos()->orderBy('descricao')->get(['id', 'codigo', 'descricao']),
            'statuses' => StatusNota::cases(),
        ]);
    }

    public function show($id)
    {
        return view('notas.show', ['nota' => NotaFiscal::query()->with(['colaborador.pessoaFisica', 'competencia', 'retencoes', 'enviadaPor', 'decididoPor'])->findOrFail($id)]);
    }

    public function enviarForm($fechamento)
    {
        $f = $this->fechamento($fechamento);

        return view('notas.form', ['fechamento' => $f, 'acao' => route('nota.enviar', $f->id), 'previa' => route('nota.previa')]);
    }

    public function previa(Request $request)
    {
        return $this->previaDoXml($request);
    }

    public function enviar(Request $request, EnviarNota $enviar, $fechamento)
    {
        return $this->enviarNota($request, $enviar, $this->fechamento($fechamento));
    }

    public function decidirForm($id)
    {
        $nota = NotaFiscal::query()->with(['colaborador.pessoaFisica', 'competencia'])->findOrFail($id);
        abort_unless($nota->status->aguardaDecisao(), 403, 'Esta nota já foi decidida.');

        return view('notas.decidir', ['nota' => $nota]);
    }

    public function decidir(Request $request, AprovarNota $aprovar, RecusarNota $recusar, $id)
    {
        $nota = NotaFiscal::findOrFail($id);

        $validos = $this->validarFormulario($request, $request->all(), [
            'decisao' => ['required', Rule::in(['aprovar', 'recusar'])],
            'texto' => ['nullable', 'string', 'max:255'],
        ], ['decisao' => 'decisão', 'texto' => 'justificativa ou motivo']);

        try {
            $validos['decisao'] === 'aprovar'
                ? $aprovar->executar($nota, $request->user(), $validos['texto'] ?? null)
                : $recusar->executar($nota, $request->user(), (string) ($validos['texto'] ?? ''));
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
        }

        return response()->json(['success' => true, 'message' => $validos['decisao'] === 'aprovar' ? 'Nota aprovada.' : 'Nota recusada; o colaborador foi avisado.', 'data' => []]);
    }

    public function arquivo($id, string $tipo): StreamedResponse
    {
        return $this->baixar(NotaFiscal::findOrFail($id), $tipo);
    }

    public function zip(Request $request, $competencia)
    {
        $c = Competencia::query()->whereIn('status', ['aprovada', 'fechada'])->findOrFail($competencia);
        GerarZipNotas::dispatch($c->id, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'O arquivo ZIP está sendo gerado. Você receberá um e-mail com o link quando ficar pronto.', 'data' => []]);
    }

    public function zipBaixar(Request $request, string $arquivo): StreamedResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]+\.zip$/', $arquivo) === 1, 404);
        // O link é de quem pediu o ZIP: encaminhado a outro usuário, não funciona.
        abort_unless((int) $request->query('usuario') === $request->user()->id, 403);
        $disco = Storage::disk(config('cadastros.notas.disco'));
        abort_unless($disco->exists('notas-zip/'.$arquivo), 404);

        return $disco->download('notas-zip/'.$arquivo, 'notas-fiscais.zip');
    }

    private function fechamento($id): Fechamento
    {
        return Fechamento::query()->with(['competencia', 'nota', 'colaborador.pessoaFisica'])->where('status', StatusFechamento::AguardandoNota->value)->findOrFail($id);
    }
}

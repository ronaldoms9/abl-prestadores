<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Ferias\CancelarFerias;
use App\Actions\Ferias\SolicitarFerias;
use App\Actions\Notas\EnviarNota;
use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Notas\RecebeNota;
use App\Models\Colaborador;
use App\Models\Fechamento;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\NotaFiscal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal do colaborador (E5, fatia 2). Tudo parte do colaborador ligado ao
 * usuário pela pessoa física: nenhuma rota recebe o id de um colaborador.
 */
class PortalController extends Controller
{
    use RecebeNota, ValidaFormulario;

    public function index(Request $request)
    {
        $colaborador = $this->colaborador($request);

        return view('portal.index', [
            'colaborador' => $colaborador,
            'saldo' => FeriasPeriodo::query()->where('colaborador_id', $colaborador->id)->get()->sum(fn (FeriasPeriodo $p) => $p->saldo()),
            'feriasPendentes' => FeriasSolicitacao::query()->where('colaborador_id', $colaborador->id)->where('status', 'pendente')->count(),
            'notasAEnviar' => $this->fechamentos($colaborador)->filter(fn (Fechamento $f) => $f->nota === null || $f->nota->status->aceitaEnvio())->count(),
        ]);
    }

    public function ferias(Request $request)
    {
        $colaborador = $this->colaborador($request);

        return view('portal.ferias', [
            'colaborador' => $colaborador,
            'periodos' => FeriasPeriodo::query()->where('colaborador_id', $colaborador->id)->orderBy('aquisitivo_inicio')->get(),
            'solicitacoes' => FeriasSolicitacao::query()->where('colaborador_id', $colaborador->id)->orderByDesc('inicio')->get(),
        ]);
    }

    public function feriasSolicitar(Request $request, SolicitarFerias $solicitar)
    {
        $colaborador = $this->colaborador($request);

        $validos = $this->validarFormulario($request, $request->all(), [
            'periodo_id' => ['required', Rule::exists('ferias_periodos', 'id')->where('colaborador_id', $colaborador->id)],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], ['periodo_id' => 'período aquisitivo', 'inicio' => 'início', 'observacao' => 'observação']);

        try {
            $solicitar->executar($colaborador, FeriasPeriodo::findOrFail($validos['periodo_id']), Carbon::parse($validos['inicio']), Carbon::parse($validos['fim']), $request->user(), $validos['observacao'] ?? null);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Solicitação enviada para aprovação.', 'data' => []]);
    }

    public function feriasCancelar(Request $request, CancelarFerias $cancelar, $id)
    {
        $solicitacao = FeriasSolicitacao::query()->where('colaborador_id', $this->colaborador($request)->id)->where('status', 'pendente')->findOrFail($id);

        try {
            $cancelar->executar($solicitacao);
        } catch (ValidationException $e) {
            return $this->recusa($e);
        }

        return response()->json(['success' => true, 'message' => 'Solicitação cancelada.', 'data' => []]);
    }

    public function notas(Request $request)
    {
        return view('portal.notas', ['colaborador' => $this->colaborador($request), 'fechamentos' => $this->fechamentos($this->colaborador($request))]);
    }

    public function notaForm(Request $request, $fechamento)
    {
        $f = $this->fechamento($request, $fechamento);
        abort_unless($f->nota === null || $f->nota->status->aceitaEnvio(), 403, 'Esta nota já foi enviada.');

        return view('notas.form', [
            'fechamento' => $f,
            'acao' => route('portal.nota.enviar', $f->id),
            'previa' => route('portal.nota.previa'),
        ]);
    }

    public function notaPrevia(Request $request)
    {
        $this->colaborador($request);

        return $this->previaDoXml($request);
    }

    public function notaEnviar(Request $request, EnviarNota $enviar, $fechamento)
    {
        $f = $this->fechamento($request, $fechamento);

        return $this->enviarNota($request, $enviar, $f);
    }

    public function notaArquivo(Request $request, $id, string $tipo): StreamedResponse
    {
        $nota = NotaFiscal::query()->where('colaborador_id', $this->colaborador($request)->id)->findOrFail($id);

        return $this->baixar($nota, $tipo);
    }

    private function colaborador(Request $request): Colaborador
    {
        $pessoa = $request->user()->pessoa_fisica_id;
        abort_if($pessoa === null, 403, 'Seu usuário não está ligado a uma pessoa. Peça ao RH para fazer a ligação.');

        $colaborador = Colaborador::query()
            ->where('pessoa_fisica_id', $pessoa)
            ->whereIn('status', [StatusColaborador::Ativo->value, StatusColaborador::Desligado->value])
            ->orderByRaw("status = 'ativo' desc")->orderByDesc('id')
            ->first();

        abort_if($colaborador === null, 403, 'Seu usuário não está ligado a um colaborador ativo. Peça ao RH para conferir o cadastro.');

        return $colaborador;
    }

    /** @return \Illuminate\Support\Collection<int, Fechamento> */
    private function fechamentos(Colaborador $colaborador)
    {
        return Fechamento::query()
            ->with(['competencia', 'nota'])
            ->where('colaborador_id', $colaborador->id)
            ->where('status', StatusFechamento::AguardandoNota->value)
            ->get()->sortByDesc(fn (Fechamento $f) => $f->competencia->ano_mes)->values();
    }

    private function fechamento(Request $request, $id): Fechamento
    {
        return Fechamento::query()->with(['competencia', 'nota', 'colaborador.pessoaFisica'])
            ->where('colaborador_id', $this->colaborador($request)->id)
            ->where('status', StatusFechamento::AguardandoNota->value)
            ->findOrFail($id);
    }

    private function recusa(ValidationException $e)
    {
        return response()->json(['success' => false, 'message' => implode(' ', $e->validator->errors()->all()), 'data' => null], 422);
    }
}

<?php

namespace App\Http\Controllers\Contratos;

use App\Actions\Contratos\GerarAditivosEmMassa;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Http\Controllers\Cadastros\ValidaFormulario;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Contrato;
use Illuminate\Http\Request;

/** Rascunhos de aditivo criados sozinhos (reajuste, cargo, benefícios): conferir e gerar em massa (E3, fatia 9). */
class AditivoPendenteController extends Controller
{
    use ValidaFormulario;

    public const LIMITE = 200;

    public function index()
    {
        return view('contratos.aditivos-pendentes', [
            'rascunhos' => $this->rascunhos()->with(['pai', 'modelo'])->orderBy('id')->limit(self::LIMITE + 1)->get(),
            'limite' => self::LIMITE,
        ]);
    }

    public function gerar(Request $request, GerarAditivosEmMassa $massa)
    {
        $validos = $this->validarFormulario($request, $request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::LIMITE],
            'ids.*' => ['integer'],
        ], ['ids' => 'aditivos']);

        $resultado = $massa->executar($this->rascunhos()->with('colaborador.pessoaFisica')->whereIn('contratos.id', $validos['ids'])->orderBy('id')->get());

        $mensagem = $resultado['gerados'].' aditivo(s) gerado(s).';
        if ($resultado['falhas'] !== []) {
            $mensagem .= ' '.count($resultado['falhas']).' não saiu(ram): '.implode(' | ', array_map(fn ($f) => $f['colaborador'].' — '.$f['mensagem'], $resultado['falhas']));
        }

        return response()->json(['success' => $resultado['falhas'] === [], 'message' => $mensagem, 'data' => $resultado], $resultado['falhas'] === [] ? 200 : 207);
    }

    private function rascunhos()
    {
        return Contrato::query()
            ->with(['colaborador.pessoaFisica'])
            ->where('tipo', TipoContrato::Aditivo->value)
            ->where('status', StatusContrato::Rascunho->value)
            ->whereIn('contratos.colaborador_id', Colaborador::visiveisPara(auth()->user())->select('colaboradores.id'));
    }
}

<?php

namespace App\Actions\Ferias;

use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\User;
use App\Notifications\FeriasSolicitada;
use App\Support\Auditoria;
use App\Support\Competencias\PrimeiroMesFechado;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Registra uma solicitação de férias e a coloca na fila de aprovação.
 * Devolve a solicitação e os avisos de conflito (não bloqueiam).
 */
class SolicitarFerias
{
    public function __construct(
        private readonly SincronizarFilaFerias $fila,
        private readonly ConflitosFerias $conflitos,
        private readonly PrimeiroMesFechado $meses,
    ) {
    }

    /** @return array{solicitacao: FeriasSolicitacao, avisos: list<string>} */
    public function executar(Colaborador $colaborador, FeriasPeriodo $periodo, CarbonInterface $inicio, CarbonInterface $fim, ?User $solicitante, ?string $observacao = null): array
    {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->startOfDay();

        $resultado = DB::transaction(function () use ($colaborador, $periodo, $inicio, $fim, $solicitante, $observacao) {
            $periodo = FeriasPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $this->validar($colaborador, $periodo, $inicio, $fim);

            $solicitacao = FeriasSolicitacao::create([
                'colaborador_id' => $colaborador->id,
                'ferias_periodo_id' => $periodo->id,
                'inicio' => $inicio,
                'fim' => $fim,
                'dias' => (int) $inicio->diffInDays($fim) + 1,
                'status' => StatusSolicitacaoFerias::Pendente,
                'solicitado_por' => $solicitante?->id,
                'nivel_atual' => 1,
                'observacao' => $observacao,
            ]);
            $solicitacao->setRelation('colaborador', $colaborador);

            Auditoria::registrar('ferias-solicitada', $colaborador, ['inicio' => $inicio->toDateString(), 'fim' => $fim->toDateString(), 'dias' => $solicitacao->dias]);

            return [$solicitacao, $this->fila->executar($solicitacao)];
        });

        [$solicitacao, $fila] = $resultado;

        // Fora da transação: um e-mail com problema não desfaz a solicitação.
        Notification::send($fila['usuarios'], new FeriasSolicitada($solicitacao));

        return ['solicitacao' => $solicitacao, 'avisos' => $this->avisos($colaborador, $inicio, $fim, $solicitacao->id)];
    }

    /** @return list<string> */
    private function avisos(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim, int $id): array
    {
        $outros = $this->conflitos->para($colaborador, $inicio, $fim, $id);

        return $outros === [] ? [] : ['Na mesma equipe ou obra, também de férias no período: '.implode(', ', $outros).'.'];
    }

    private function validar(Colaborador $colaborador, FeriasPeriodo $periodo, CarbonInterface $inicio, CarbonInterface $fim): void
    {
        $erro = fn (string $campo, string $mensagem) => throw ValidationException::withMessages([$campo => $mensagem]);

        if ($colaborador->status !== StatusColaborador::Ativo) {
            $erro('colaborador', 'Só colaborador ativo pode ter férias solicitadas.');
        }
        if ($periodo->colaborador_id !== $colaborador->id) {
            $erro('periodo', 'O período aquisitivo não é deste colaborador.');
        }
        if ($fim->lt($inicio)) {
            $erro('fim', 'O fim não pode ser antes do início.');
        }
        if ($inicio->lte($periodo->aquisitivo_fim)) {
            $erro('inicio', 'As férias só podem começar depois de completado o período aquisitivo (em '.$periodo->aquisitivo_fim->copy()->addDay()->format('d/m/Y').').');
        }
        if ($colaborador->data_admissao !== null && $inicio->lt($colaborador->data_admissao)) {
            $erro('inicio', 'As férias não podem começar antes da admissão.');
        }
        if ($colaborador->data_desligamento !== null && $fim->gt($colaborador->data_desligamento)) {
            $erro('fim', 'As férias passam da data de desligamento ('.$colaborador->data_desligamento->format('d/m/Y').').');
        }

        $dias = (int) $inicio->diffInDays($fim) + 1;
        $reservados = (int) FeriasSolicitacao::query()
            ->where('ferias_periodo_id', $periodo->id)
            ->where('status', StatusSolicitacaoFerias::Pendente->value)
            ->sum('dias');
        $livre = $periodo->saldo() - $reservados;

        if ($dias > $livre) {
            $erro('dias', "O período tem {$livre} dia(s) livre(s) (saldo {$periodo->saldo()}, {$reservados} reservado(s) em solicitações pendentes); foram pedidos {$dias}.");
        }

        $minimo = $this->minimoDeDias();
        if ($dias < $minimo && $dias !== $livre) {
            $erro('dias', "Cada solicitação tem de ter pelo menos {$minimo} dias (ou o saldo que sobrou).");
        }

        $sobrepoe = FeriasSolicitacao::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereIn('status', [StatusSolicitacaoFerias::Pendente->value, StatusSolicitacaoFerias::Aprovada->value])
            ->whereDate('inicio', '<=', $fim)->whereDate('fim', '>=', $inicio)
            ->exists();
        if ($sobrepoe) {
            $erro('inicio', 'Já existe solicitação pendente ou aprovada que se sobrepõe a esse período.');
        }

        if ($fechado = $this->meses->em($inicio, $fim)) {
            $erro('inicio', 'A competência de '.$fechado->format('m/Y').' já está fechada. As férias não podem ser lançadas nesse mês.');
        }
    }

    private function minimoDeDias(): int
    {
        $valor = Configuracao::obter('ferias.fracionamento_minimo_dias');

        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : (int) config('cadastros.ferias.fracionamento_minimo_dias');
    }
}

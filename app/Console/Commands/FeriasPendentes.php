<?php

namespace App\Console\Commands;

use App\Actions\Ferias\SincronizarFilaFerias;
use App\Enums\StatusSolicitacaoFerias;
use App\Models\FeriasSolicitacao;
use App\Models\User;
use App\Notifications\ResumoFeriasPendentes;
use Illuminate\Console\Command;

/**
 * Lembrete diário aos aprovadores. Como a fila é calculada no dia, quem passou
 * a ser aprovador por troca de equipe também recebe. Reavalia cada solicitação
 * pendente (registrando escaladas) antes de avisar.
 */
class FeriasPendentes extends Command
{
    protected $signature = 'ferias:pendentes';

    protected $description = 'Avisa cada aprovador das solicitações de férias que esperam a sua decisão';

    public function handle(SincronizarFilaFerias $fila): int
    {
        $porUsuario = [];
        $usuarios = [];

        FeriasSolicitacao::query()
            ->with(['colaborador.pessoaFisica'])
            ->where('status', StatusSolicitacaoFerias::Pendente->value)
            ->orderBy('inicio')
            ->each(function (FeriasSolicitacao $s) use ($fila, &$porUsuario, &$usuarios) {
                foreach ($fila->executar($s)['usuarios'] as $usuario) {
                    $usuarios[$usuario->id] = $usuario;
                    $porUsuario[$usuario->id][] = $s;
                }
            });

        foreach ($porUsuario as $id => $solicitacoes) {
            /** @var User $usuario */
            $usuario = $usuarios[$id];
            $usuario->notify(new ResumoFeriasPendentes(collect($solicitacoes)));
        }

        $this->info(sprintf('Lembrete enviado a %d aprovador(es).', count($porUsuario)));

        return self::SUCCESS;
    }
}

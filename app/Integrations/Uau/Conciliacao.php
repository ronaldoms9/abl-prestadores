<?php

namespace App\Integrations\Uau;

use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Integrations\Uau\Jobs\ConcluirSincronizacaoJob;
use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use App\Models\Sincronizacao;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cadeia diária de conciliação com o UAU (D5.9 e 5.4 da fatia 5).
 *
 * Roda na fila 'uau' em série: empresas -> obras -> conciliação de pessoas.
 * Se um elo falhar, os seguintes não rodam. disparar() registra a execução em
 * sincronizacoes (D8.2 da fatia 8); é o que o agendamento e o botão usam.
 */
final class Conciliacao
{
    /** Uma sincronização em andamento há mais que isto é dada como interrompida (o uniqueFor dos jobs). */
    public const LIMITE_EM_ANDAMENTO_SEGUNDOS = 3600;

    public static function cadeia(): PendingChain
    {
        return Bus::chain([
            new ImportarEmpresasJob(),
            new ImportarObrasJob(),
            new ConciliarPessoasJob(),
        ])->onQueue('uau');
    }

    /**
     * Registra a sincronização e despacha a cadeia, com um último elo que a
     * conclui e um catch que a marca como falha.
     *
     * @throws SincronizacaoEmAndamento
     */
    public static function disparar(OrigemSincronizacao $origem): Sincronizacao
    {
        $sincronizacao = Cache::lock('uau:disparar-sincronizacao', 10)->block(5, function () use ($origem) {
            self::encerrarInterrompidas();

            if ($emAndamento = Sincronizacao::emAndamento()->latest('iniciada_em')->first()) {
                throw new SincronizacaoEmAndamento($emAndamento);
            }

            return Sincronizacao::create([
                'origem' => $origem,
                'status' => StatusSincronizacao::EmAndamento,
                'iniciada_em' => now(),
            ]);
        });

        $id = $sincronizacao->id;

        self::cadeia()
            ->append(new ConcluirSincronizacaoJob($id))
            ->catch(function (Throwable $erro) use ($id) {
                Sincronizacao::find($id)?->falhar($erro->getMessage());
            })
            ->dispatch();

        return $sincronizacao;
    }

    private static function encerrarInterrompidas(): void
    {
        Sincronizacao::emAndamento()
            ->where('iniciada_em', '<', now()->subSeconds(self::LIMITE_EM_ANDAMENTO_SEGUNDOS))
            ->get()
            ->each(fn (Sincronizacao $antiga) => $antiga->falhar('Interrompida: o worker parou antes de concluir.'));
    }
}

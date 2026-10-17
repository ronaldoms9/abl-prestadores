<?php

namespace App\Http\View\Composers;

use App\Models\IntegracaoLog;
use App\Models\Sincronizacao;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Indicador de integrações no topo da tela (D8.5 da fatia 8): falhas sem
 * solução nas últimas 24 h e a última sincronização concluída. Só para quem
 * tem integracao-list. O número é o mesmo para todos: cache único de 60 s.
 */
class IndicadorIntegracao
{
    public const SEGUNDOS_CACHE = 60;

    public function compose(View $view): void
    {
        if (! auth()->user()?->can('integracao-list')) {
            $view->with('indicadorIntegracao', null);

            return;
        }

        $view->with('indicadorIntegracao', Cache::remember(
            Sincronizacao::CACHE_INDICADOR,
            self::SEGUNDOS_CACHE,
            fn () => [
                'falhas' => IntegracaoLog::semSolucao()->where('executado_em', '>=', now()->subDay())->count(),
                'ultima' => Sincronizacao::ultimaConcluida()?->concluida_em,
            ],
        ));
    }
}

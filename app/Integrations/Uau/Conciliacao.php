<?php

namespace App\Integrations\Uau;

use App\Integrations\Uau\Jobs\ConciliarPessoasJob;
use App\Integrations\Uau\Jobs\ImportarEmpresasJob;
use App\Integrations\Uau\Jobs\ImportarObrasJob;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;

/**
 * Cadeia diária de conciliação com o UAU (D5.9 e 5.4 da fatia 5).
 *
 * Roda na fila 'uau' em série: empresas -> obras -> conciliação de pessoas.
 * Se um elo falhar, os seguintes não rodam.
 */
final class Conciliacao
{
    public static function cadeia(): PendingChain
    {
        return Bus::chain([
            new ImportarEmpresasJob(),
            new ImportarObrasJob(),
            new ConciliarPessoasJob(),
        ])->onQueue('uau');
    }
}

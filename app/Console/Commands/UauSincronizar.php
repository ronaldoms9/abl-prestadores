<?php

namespace App\Console\Commands;

use App\Enums\OrigemSincronizacao;
use App\Integrations\Uau\Conciliacao;
use App\Integrations\Uau\SincronizacaoEmAndamento;
use Illuminate\Console\Command;

/**
 * Dispara a conciliação completa com o UAU (empresas, obras e pessoas) na fila
 * uau. É o que o agendamento diário chama (D6.1 da fatia 6); serve também à mão no servidor.
 */
class UauSincronizar extends Command
{
    protected $signature = 'uau:sincronizar';

    protected $description = 'Dispara a conciliação completa com o UAU (empresas, obras e pessoas)';

    public function handle(): int
    {
        try {
            $sincronizacao = Conciliacao::disparar(OrigemSincronizacao::Agendada);
        } catch (SincronizacaoEmAndamento $e) {
            // Não é erro: uma sincronização manual pode ter começado minutos antes.
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        $this->info("Sincronização {$sincronizacao->id} enviada para a fila uau. Acompanhe em Integrações.");

        return self::SUCCESS;
    }
}

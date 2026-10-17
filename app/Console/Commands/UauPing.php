<?php

namespace App\Console\Commands;

use App\Integrations\Uau\Exceptions\UauException;
use App\Integrations\Uau\UauGateway;
use Illuminate\Console\Command;

/**
 * Valida a conexão e as credenciais do UAU no servidor, sem mostrar segredos
 * nem dados pessoais. Com --documento, confirma como a API responde a uma
 * pessoa encontrada ou não encontrada (decisão D4.5 do plano da fatia 4).
 */
class UauPing extends Command
{
    protected $signature = 'uau:ping {--documento= : CPF ou CNPJ para testar a consulta de pessoa}';

    protected $description = 'Testa a conexão e as credenciais da API do UAU';

    public function handle(): int
    {
        $this->line(config('uau.fake')
            ? 'Modo: simulado (UAU_FAKE=true), com os dados de resources/fixtures/uau.'
            : 'Modo: real. URL: '.config('uau.base_url'));

        try {
            $uau = $this->laravel->make(UauGateway::class);

            $inicio = hrtime(true);
            $versao = $uau->versao();
            $this->info(sprintf('Conectado. Versão do UAU: %s (%d ms).', $versao, (hrtime(true) - $inicio) / 1_000_000));

            if ($documento = $this->option('documento')) {
                $pessoa = $uau->pessoaPorDocumento($documento);

                $this->line($pessoa === null
                    ? 'Pessoa não encontrada.'
                    : sprintf('Pessoa encontrada: %s, código %d, %s.', $pessoa->tipo->rotulo(), $pessoa->codigo, $pessoa->ativa ? 'ativa' : 'inativa'));
            }
        } catch (UauException $e) {
            $this->error('Falhou: '.$e->getMessage());

            if ($e->logId !== null) {
                $this->line("Detalhes em integracao_logs, id {$e->logId}.");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

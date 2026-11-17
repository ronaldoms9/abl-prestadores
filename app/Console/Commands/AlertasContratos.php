<?php

namespace App\Console\Commands;

use App\Actions\Contratos\ContratosAVencer;
use App\Models\Configuracao;
use App\Notifications\ResumoContratos;
use App\Support\Alertas\DestinatariosAlerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta diário de contratos: um e-mail-resumo para todo usuário ativo com a
 * permissão alerta-contratos. Avisa nos marcos de 30, 15 e 5 dias antes do fim
 * (e no dia) e todo dia enquanto o contrato estiver vencido. Sem nada a avisar,
 * não envia. Sem destinatário, registra no log e avisa o administrador (README D2).
 * Os marcos podem ser mudados em `configuracoes` (alertas.contratos, campo dias).
 */
class AlertasContratos extends Command
{
    public const PERMISSAO = 'alerta-contratos';

    protected $signature = 'alertas:contratos';

    protected $description = 'Envia o resumo diário de contratos vencidos e a vencer (30, 15 e 5 dias)';

    public function handle(ContratosAVencer $contratos): int
    {
        $marcos = array_map('intval', (array) (Configuracao::obter('alertas.contratos', [])['dias'] ?? config('cadastros.contratos.alerta_dias')));
        $hoje = now();

        $destinatarios = DestinatariosAlerta::comPermissao(self::PERMISSAO, 'alerta de contratos vencidos e a vencer');
        if ($destinatarios->isEmpty()) {
            $this->warn('Ninguém tem a permissão '.self::PERMISSAO.'. Aviso registrado no painel do administrador.');

            return self::SUCCESS;
        }

        $resultado = $contratos->consultar($marcos, $hoje);
        if ($resultado['vencidos']->isEmpty() && $resultado['marcos']->isEmpty()) {
            $this->info('Nenhum contrato vencido nem em marco de vencimento ('.implode(', ', $marcos).' dias).');

            return self::SUCCESS;
        }

        Notification::send($destinatarios, new ResumoContratos($resultado['vencidos'], $resultado['marcos'], $hoje));

        $this->info(sprintf('Resumo enviado a %d usuário(s): %d vencido(s), %d em marco.', $destinatarios->count(), $resultado['vencidos']->count(), $resultado['marcos']->count()));

        return self::SUCCESS;
    }
}

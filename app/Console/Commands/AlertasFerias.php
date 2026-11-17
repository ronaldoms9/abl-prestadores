<?php

namespace App\Console\Commands;

use App\Actions\Ferias\FeriasAVencer;
use App\Models\Configuracao;
use App\Notifications\ResumoFerias;
use App\Support\Alertas\DestinatariosAlerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta diário de férias: um e-mail-resumo para todo usuário ativo com a
 * permissão alerta-ferias, com os saldos cujo limite de gozo venceu ou chega em
 * até N dias (configuracoes[alertas.ferias].dias, padrão 60). Sem nada a
 * avisar, não envia. Sem destinatário, registra no log e avisa o
 * administrador (README D2).
 */
class AlertasFerias extends Command
{
    public const PERMISSAO = 'alerta-ferias';

    protected $signature = 'alertas:ferias';

    protected $description = 'Envia o resumo diário de saldos de férias perto do limite de gozo';

    public function handle(FeriasAVencer $ferias): int
    {
        $dias = (int) (Configuracao::obter('alertas.ferias', [])['dias'] ?? config('cadastros.ferias.alerta_dias'));
        $hoje = now();

        $destinatarios = DestinatariosAlerta::comPermissao(self::PERMISSAO, 'alerta de saldos de férias perto do limite');
        if ($destinatarios->isEmpty()) {
            $this->warn('Ninguém tem a permissão '.self::PERMISSAO.'. Aviso registrado no painel do administrador.');

            return self::SUCCESS;
        }

        $resultado = $ferias->consultar($dias, $hoje);
        if ($resultado['vencidos']->isEmpty() && $resultado['a_vencer']->isEmpty()) {
            $this->info("Nenhum saldo de férias vencido nem a {$dias} dias do limite.");

            return self::SUCCESS;
        }

        Notification::send($destinatarios, new ResumoFerias($resultado['vencidos'], $resultado['a_vencer'], $dias, $hoje));

        $this->info(sprintf('Resumo enviado a %d usuário(s): %d vencido(s), %d a vencer.', $destinatarios->count(), $resultado['vencidos']->count(), $resultado['a_vencer']->count()));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\Qualificacoes\SituacaoQualificacoes;
use App\Models\Configuracao;
use App\Notifications\ResumoQualificacoes;
use App\Support\Alertas\DestinatariosAlerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta diário de qualificações: um e-mail-resumo para todo usuário ativo com
 * a permissão alerta-qualificacoes, igual ao de documentos. Sem pendência, não
 * envia; sem destinatário, registra no log e avisa o administrador (README D2).
 */
class AlertasQualificacoes extends Command
{
    public const PERMISSAO = 'alerta-qualificacoes';

    protected $signature = 'alertas:qualificacoes';

    protected $description = 'Envia o resumo diário de qualificações obrigatórias ausentes, vencidas e a vencer';

    public function handle(SituacaoQualificacoes $qualificacoes): int
    {
        $dias = (int) (Configuracao::obter('alertas.qualificacoes', [])['dias'] ?? config('cadastros.alertas.qualificacoes_dias'));
        $hoje = now();

        $destinatarios = DestinatariosAlerta::comPermissao(self::PERMISSAO, 'alerta de qualificações ausentes, vencidas e a vencer');
        if ($destinatarios->isEmpty()) {
            $this->warn('Ninguém tem a permissão '.self::PERMISSAO.'. Aviso registrado no painel do administrador.');

            return self::SUCCESS;
        }

        $situacoes = $qualificacoes->analisar($hoje, $dias);
        if ($situacoes->isEmpty()) {
            $this->info('Nenhuma qualificação obrigatória ausente, vencida ou a vencer em '.$dias.' dias.');

            return self::SUCCESS;
        }

        Notification::send($destinatarios, new ResumoQualificacoes($situacoes, $dias, $hoje));

        $this->info(sprintf('Resumo enviado a %d usuário(s) sobre %d colaborador(es).', $destinatarios->count(), $situacoes->count()));

        return self::SUCCESS;
    }
}

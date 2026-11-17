<?php

namespace App\Console\Commands;

use App\Actions\Documentos\DocumentosAVencer;
use App\Models\Configuracao;
use App\Notifications\ResumoDocumentos;
use App\Support\Alertas\DestinatariosAlerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta diário de documentos: um e-mail-resumo para todo usuário ativo com a
 * permissão alerta-documentos. Sem documentos vencidos nem a vencer, não envia
 * nada. Sem destinatário, registra no log e avisa o administrador (README D2).
 */
class AlertasDocumentos extends Command
{
    public const PERMISSAO = 'alerta-documentos';

    protected $signature = 'alertas:documentos';

    protected $description = 'Envia o resumo diário de documentos vencidos e a vencer';

    public function handle(DocumentosAVencer $documentos): int
    {
        $dias = (int) (Configuracao::obter('alertas.documentos', [])['dias'] ?? config('cadastros.alertas.documentos_dias'));
        $hoje = now();

        $destinatarios = DestinatariosAlerta::comPermissao(self::PERMISSAO, 'alerta de documentos vencidos e a vencer');
        if ($destinatarios->isEmpty()) {
            $this->warn('Ninguém tem a permissão '.self::PERMISSAO.'. Aviso registrado no painel do administrador.');

            return self::SUCCESS;
        }

        $resultado = $documentos->consultar($dias, $hoje);
        if ($resultado['vencidos']->isEmpty() && $resultado['a_vencer']->isEmpty()) {
            $this->info('Nenhum documento vencido nem a vencer em '.$dias.' dias.');

            return self::SUCCESS;
        }

        Notification::send($destinatarios, new ResumoDocumentos($resultado['vencidos'], $resultado['a_vencer'], $dias, $hoje));

        $this->info(sprintf('Resumo enviado a %d usuário(s): %d vencido(s), %d a vencer.', $destinatarios->count(), $resultado['vencidos']->count(), $resultado['a_vencer']->count()));

        return self::SUCCESS;
    }
}

<?php

namespace App\Support;

use App\Models\Configuracao;

/**
 * Avisos que ninguém pode perder, mostrados no painel do administrador (README D2):
 * hoje, uma permissão de alerta que nenhum usuário ativo tem. Ficam em `configuracoes`.
 */
class AvisosAdmin
{
    private const CHAVE = 'avisos.alerta_sem_destinatario';

    public static function registrarAlertaSemDestinatario(string $permissao, string $descricao): void
    {
        $avisos = Configuracao::obter(self::CHAVE, []);
        $avisos[$permissao] ??= ['descricao' => $descricao, 'desde' => now()->toDateString()];

        Configuracao::definir(self::CHAVE, $avisos);
    }

    public static function limparAlertaSemDestinatario(string $permissao): void
    {
        $avisos = Configuracao::obter(self::CHAVE, []);

        if (! isset($avisos[$permissao])) {
            return;
        }

        unset($avisos[$permissao]);
        $avisos === [] ? Configuracao::esquecer(self::CHAVE) : Configuracao::definir(self::CHAVE, $avisos);
    }

    /** @return array<string, array{descricao: string, desde: string}> permissão => aviso */
    public static function alertasSemDestinatario(): array
    {
        return Configuracao::obter(self::CHAVE, []);
    }
}

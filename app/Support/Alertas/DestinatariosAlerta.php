<?php

namespace App\Support\Alertas;

use App\Models\User;
use App\Support\AvisosAdmin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Quem recebe cada alerta: todo usuário ativo com a permissão do alerta
 * (README D2). Sem ninguém, o sistema registra no log e deixa um aviso no
 * painel do administrador em vez de falhar em silêncio; com alguém, o aviso some.
 */
class DestinatariosAlerta
{
    /** @return Collection<int, User> */
    public static function comPermissao(string $permissao, string $descricao): Collection
    {
        $usuarios = User::query()
            ->ativos()
            ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', $permissao))
            ->orderBy('id')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::warning("Alerta sem destinatário: ninguém ativo tem a permissão {$permissao} ({$descricao}).");
            AvisosAdmin::registrarAlertaSemDestinatario($permissao, $descricao);
        } else {
            AvisosAdmin::limparAlertaSemDestinatario($permissao);
        }

        return $usuarios;
    }
}

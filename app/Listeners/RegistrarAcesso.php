<?php

namespace App\Listeners;

use App\Support\Auditoria;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Login, logout e falha de login na auditoria. A senha digitada nunca é gravada.
 */
class RegistrarAcesso
{
    public function login(Login $event): void
    {
        Auditoria::registrar('login', autor: $event->user);
    }

    public function logout(Logout $event): void
    {
        if ($event->user) {
            Auditoria::registrar('logout', autor: $event->user);
        }
    }

    public function falha(Failed $event): void
    {
        Auditoria::registrar('login-falhou', dados: [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }
}

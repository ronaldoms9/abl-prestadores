<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de redefinição de senha, em português e com o nome do sistema.
 */
class RedefinirSenha extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $validade = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha — '.config('app.name'))
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line("Este link vale por {$validade} minutos.")
            ->line('Se você não fez esse pedido, ignore este e-mail: sua senha continua a mesma.');
    }
}

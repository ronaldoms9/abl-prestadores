<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desconecta, na próxima requisição, quem foi desativado com a sessão aberta.
 * Cobre também o "lembrar-me" e o login automático após redefinir a senha.
 */
class UsuarioAtivo
{
    public const MENSAGEM = 'Seu acesso foi desativado.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->ativo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => self::MENSAGEM], 401);
            }

            return redirect()->route('login')->withErrors(['email' => self::MENSAGEM]);
        }

        return $next($request);
    }
}

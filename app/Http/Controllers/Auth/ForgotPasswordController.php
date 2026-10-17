<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * E-mail inexistente e pedido repetido (throttle, que só ocorre para
     * contas existentes) respondem como sucesso, para não revelar se a conta
     * existe.
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        if (in_array($response, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($response)]);
    }
}

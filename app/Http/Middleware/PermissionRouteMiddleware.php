<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionRouteMiddleware
{

    protected $auth;

     /**
	 * Creates a new instance of the middleware.
	 *
	 * @param Guard $auth
	 */
	public function __construct(Guard $auth)
	{
		$this->auth = $auth;
	}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $params): Response
    {
        $params = explode(':', $params);

        $all = isset($params[1]);

        $permissions = explode('|', $params[0]);

        if ($this->auth->guest() || !$request->user()->can($permissions, $all)) {
            if($request->is('api/*') || $request->isJson()){
                return response()->json(['message' => "Você não tem permissão para acessar está funcionalidade"], 403);
            }else{
                abort(403, "Você não tem permissão para acessar esta função");
            }
        }

        return $next($request);
    }
}

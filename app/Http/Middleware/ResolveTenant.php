<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set($request->user()?->tenant);

        return $next($request);
    }
}

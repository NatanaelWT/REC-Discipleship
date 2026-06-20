<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUserContext;
use App\Support\RuntimeBootstrap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        RuntimeBootstrap::boot($request);

        if (! app(CurrentUserContext::class)->isLoggedIn()) {
            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}

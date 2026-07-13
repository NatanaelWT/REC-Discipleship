<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecPageAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $pageKey): Response
    {
        $context = app(CurrentUserContext::class);
        if (! $context->isLoggedIn()) {
            return redirect()->route('auth.login');
        }

        if (! $context->canAccessPage($pageKey)) {
            return redirect(AppPageRouteMap::pageUrl($context->homePage(), ['error' => 'access_denied']));
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ExpireLegacyAnalyticsIdentity
{
    private const COOKIE_NAME = 'rec_analytics_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $request->session()->forget('analytics.visitor_id');
        }

        $response = $next($request);
        if ($request->cookies->has(self::COOKIE_NAME)) {
            $response->headers->setCookie(Cookie::forget(self::COOKIE_NAME));
        }

        return $response;
    }
}

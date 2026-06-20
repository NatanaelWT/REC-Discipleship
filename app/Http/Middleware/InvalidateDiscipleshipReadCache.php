<?php

namespace App\Http\Middleware;

use App\Services\Discipleship\DiscipleshipReadCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidateDiscipleshipReadCache
{
    public function __construct(private readonly DiscipleshipReadCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethodSafe() || $response->getStatusCode() >= 500) {
            return $response;
        }

        $routeName = (string) optional($request->route())->getName();
        if (str_starts_with($routeName, 'discipleship.')
            || str_starts_with($routeName, 'public.dg.')) {
            $this->cache->invalidate();
        }

        return $response;
    }
}

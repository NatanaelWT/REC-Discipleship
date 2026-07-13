<?php

namespace App\Http\Middleware;

use App\Support\RuntimeBootstrap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapRuntime
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        RuntimeBootstrap::bootHttpRequest($request);

        $response = $next($request);

        if (app()->environment('testing')) {
            $response->headers->set(
                'X-Runtime-Bootstrap-Count',
                (string) RuntimeBootstrap::httpBootCount($request),
            );
            $response->headers->set(
                'X-Runtime-Helper-Count',
                (string) RuntimeBootstrap::httpHelperCount($request),
            );
        }

        return $response;
    }
}

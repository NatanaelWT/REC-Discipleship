<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectLegacyPageQuery
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query->has('page') && ! $this->allowsPageQuery($request)) {
            abort(404);
        }

        return $next($request);
    }

    private function allowsPageQuery(Request $request): bool
    {
        return $request->is('pemuridan/anggota/rows')
            || $request->is('pemuridan/kelompok/rows')
            || $request->is('pemuridan/msk/rows')
            || $request->is('pemuridan/spiritual-journey/rows');
    }
}

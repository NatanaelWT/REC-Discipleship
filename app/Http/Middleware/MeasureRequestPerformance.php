<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'staging'])) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $databaseMs = 0.0;
        DB::listen(static function ($query) use (&$queryCount, &$databaseMs): void {
            $queryCount++;
            $databaseMs += (float) $query->time;
        });

        $response = $next($request);
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d queries"',
            $durationMs,
            $databaseMs,
            $queryCount,
        ));
        $response->headers->set('X-Query-Count', (string) $queryCount);

        if ($durationMs >= 1000) {
            Log::warning('Slow application request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => round($durationMs, 1),
                'database_ms' => round($databaseMs, 1),
                'query_count' => $queryCount,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1_048_576, 1),
                'response_kb' => round(strlen((string) $response->getContent()) / 1024, 1),
            ]);
        }

        return $response;
    }
}

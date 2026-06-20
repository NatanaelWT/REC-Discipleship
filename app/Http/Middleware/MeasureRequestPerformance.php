<?php

namespace App\Http\Middleware;

use App\Services\Discipleship\DiscipleshipReadCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function __construct(private readonly DiscipleshipReadCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('performance.enabled') || app()->environment(['local', 'staging']);
        if (! $enabled || ! $request->is('pemuridan/*')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $databaseMs = 0.0;
        $cacheBefore = $this->cache->metrics();
        DB::listen(static function ($query) use (&$queryCount, &$databaseMs): void {
            $queryCount++;
            $databaseMs += (float) $query->time;
        });

        $response = $next($request);
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $cacheAfter = $this->cache->metrics();
        $cacheHits = $cacheAfter['hits'] - $cacheBefore['hits'];
        $cacheMisses = $cacheAfter['misses'] - $cacheBefore['misses'];
        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d queries"',
            $durationMs,
            $databaseMs,
            $queryCount,
        ));
        $response->headers->set('X-Query-Count', (string) $queryCount);
        $response->headers->set('X-Read-Cache', sprintf('hit=%d; miss=%d', $cacheHits, $cacheMisses));

        if ($durationMs >= (int) config('performance.slow_request_ms', 1000)) {
            Log::warning('Slow application request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => round($durationMs, 1),
                'database_ms' => round($databaseMs, 1),
                'query_count' => $queryCount,
                'cache_hits' => $cacheHits,
                'cache_misses' => $cacheMisses,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1_048_576, 1),
                'response_kb' => round(strlen((string) $response->getContent()) / 1024, 1),
            ]);
        }

        return $response;
    }
}

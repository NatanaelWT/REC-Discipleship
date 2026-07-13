<?php

namespace App\Http\Middleware;

use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\Performance\RequestPerformanceMonitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    public function __construct(
        private readonly DiscipleshipReadCache $cache,
        private readonly RequestPerformanceMonitor $monitor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('performance.enabled') || app()->environment(['local', 'staging']);
        if (! $enabled || $request->is('assets/*', 'build/*', 'storage/*', '@vite/*')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $cacheBefore = $this->cache->metrics();
        $this->monitor->start();

        try {
            $response = $next($request);
        } finally {
            $metrics = $this->monitor->finish();
        }
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $cacheAfter = $this->cache->metrics();
        $cacheHits = $cacheAfter['hits'] - $cacheBefore['hits'];
        $cacheMisses = $cacheAfter['misses'] - $cacheBefore['misses'];
        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d queries"',
            $durationMs,
            $metrics['database_ms'],
            $metrics['query_count'],
        ));
        $responseBytes = $this->responseBytes($response);
        $response->headers->set('X-Query-Count', (string) $metrics['query_count']);
        $response->headers->set('X-DB-Write-Count', (string) $metrics['write_count']);
        $response->headers->set('X-Peak-Memory-Bytes', (string) $metrics['peak_memory_bytes']);
        $response->headers->set('X-Response-Bytes', (string) $responseBytes);
        $response->headers->set('X-Read-Cache', sprintf('hit=%d; miss=%d', $cacheHits, $cacheMisses));

        $logAll = (bool) config('performance.log_all', false);
        if ($logAll || $durationMs >= (int) config('performance.slow_request_ms', 1000)) {
            Log::log($logAll ? 'info' : 'warning', $logAll ? 'Application request performance' : 'Slow application request', [
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'duration_ms' => round($durationMs, 1),
                'database_ms' => round($metrics['database_ms'], 1),
                'query_count' => $metrics['query_count'],
                'database_writes' => $metrics['write_count'],
                'cache_hits' => $cacheHits,
                'cache_misses' => $cacheMisses,
                'peak_memory_mb' => round($metrics['peak_memory_bytes'] / 1_048_576, 1),
                'response_kb' => round($responseBytes / 1024, 1),
            ]);
        }

        return $response;
    }

    private function responseBytes(Response $response): int
    {
        $header = $response->headers->get('Content-Length');
        if (is_numeric($header)) {
            return max(0, (int) $header);
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : 0;
    }
}

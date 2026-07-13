<?php

namespace App\Services\Performance;

use Illuminate\Database\Events\QueryExecuted;

class RequestPerformanceMonitor
{
    private bool $active = false;

    private int $queryCount = 0;

    private int $writeCount = 0;

    private float $databaseMs = 0.0;

    public function start(): void
    {
        $this->queryCount = 0;
        $this->writeCount = 0;
        $this->databaseMs = 0.0;
        $this->active = true;
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
    }

    public function record(QueryExecuted $query): void
    {
        if (! $this->active) {
            return;
        }

        $this->queryCount++;
        $this->databaseMs += (float) $query->time;
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql) === 1) {
            $this->writeCount++;
        }
    }

    /** @return array{query_count:int,write_count:int,database_ms:float,peak_memory_bytes:int} */
    public function finish(): array
    {
        $this->active = false;

        return [
            'query_count' => $this->queryCount,
            'write_count' => $this->writeCount,
            'database_ms' => $this->databaseMs,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }
}

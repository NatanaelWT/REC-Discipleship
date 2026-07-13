<?php

namespace Tests\Concerns;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

trait RejectsTrackingQueries
{
    /** @var list<string> */
    private array $removedTrackingQueries = [];

    protected function startTrackingQueryGuard(): void
    {
        $this->removedTrackingQueries = [];

        DB::listen(function (QueryExecuted $query): void {
            if (preg_match(
                '/\b(?:aktivitas|request_activities|activity_requests|permintaan_aktivitas|audit_events|activity_events|peristiwa_aktivitas|website_page_views|kunjungan_halaman|website_daily_rollups|website_sessions|sesi|maintenance_runs)\b/i',
                $query->sql,
            ) === 1) {
                $this->removedTrackingQueries[] = $query->sql;
            }
        });
    }

    protected function assertNoTrackingQueriesWereExecuted(): void
    {
        $this->assertSame(
            [],
            $this->removedTrackingQueries,
            'Request accessed a removed activity, analytics, or maintenance table.',
        );
    }
}

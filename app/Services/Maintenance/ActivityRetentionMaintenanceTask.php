<?php

namespace App\Services\Maintenance;

use App\Contracts\MaintenanceTask;
use App\Services\Activity\ActivitySpool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ActivityRetentionMaintenanceTask implements MaintenanceTask
{
    public function __construct(private readonly ActivitySpool $spool) {}

    public function key(): string
    {
        return 'activity_retention';
    }

    public function label(): string
    {
        return 'Activity rollup dan retensi 90 hari';
    }

    public function preview(): array
    {
        $cutoff = $this->cutoff();

        return [
            'retention_days' => (int) config('activity.retention_days', 90),
            'cutoff_utc' => $cutoff->toIso8601String(),
            'raw_requests' => $this->safeCount('request_activities'),
            'expired_requests' => $this->safeCount('request_activities', 'started_at', $cutoff),
            'expired_events' => $this->safeCount('audit_events', 'occurred_at', $cutoff),
            'daily_rollups' => $this->safeCount('website_daily_rollups'),
            'legacy_rows_to_copy' => Schema::hasTable('aktivitas')
                ? DB::table('aktivitas')->where('started_at', '>=', $cutoff)->count()
                : 0,
            'legacy_cutover_policy' => 'verify_then_manual_rename_to_rollback; never_auto_drop; retain_at_least_7_days',
            'spooled_requests' => $this->spool->lineCount(),
            'spool_bytes' => $this->spool->size(),
        ];
    }

    public function run(array $cursor, int $batchSize, float $deadline): array
    {
        $phase = (string) ($cursor['phase'] ?? 'legacy_rollup');
        $stats = is_array($cursor['stats'] ?? null) ? $cursor['stats'] : [
            'legacy_rollup_days_rebuilt' => 0,
            'legacy_rollup_rows_written' => 0,
            'legacy_requests_copied' => 0,
            'legacy_events_copied' => 0,
            'spool_requests_replayed' => 0,
            'spool_requests_quarantined' => 0,
            'spool_replay_failures' => 0,
            'rollup_days_rebuilt' => 0,
            'rollup_rows_written' => 0,
            'validation_legacy_requests_checked' => 0,
            'validation_missing_requests' => 0,
            'validation_event_mismatches' => 0,
            'validation_rollup_days_checked' => 0,
            'validation_missing_rollup_days' => 0,
            'validation_rollup_metric_mismatches' => 0,
            'events_pruned' => 0,
            'requests_pruned' => 0,
            'legacy_validation_status' => 'pending',
            'legacy_validated_at' => null,
            'legacy_cutover_guidance' => null,
        ];
        foreach ([
            'legacy_rollup_days_rebuilt', 'legacy_rollup_rows_written', 'legacy_requests_copied',
            'legacy_events_copied', 'spool_requests_replayed', 'spool_requests_quarantined',
            'spool_replay_failures', 'rollup_days_rebuilt', 'rollup_rows_written',
            'validation_legacy_requests_checked', 'validation_missing_requests',
            'validation_event_mismatches', 'validation_rollup_days_checked',
            'validation_missing_rollup_days', 'validation_rollup_metric_mismatches',
            'events_pruned', 'requests_pruned',
        ] as $stat) {
            $stats[$stat] = (int) ($stats[$stat] ?? 0);
        }

        while (microtime(true) < $deadline) {
            if ($phase === 'legacy_rollup') {
                if (! Schema::hasTable('aktivitas')) {
                    $phase = 'legacy_backfill';
                    unset($cursor['legacy_rollup_date']);
                    continue;
                }
                [$complete, $nextDate, $rows] = $this->rollupDay(
                    isset($cursor['legacy_rollup_date']) ? (string) $cursor['legacy_rollup_date'] : null,
                    'aktivitas',
                );
                if ($rows !== null) {
                    $stats['legacy_rollup_days_rebuilt']++;
                    $stats['legacy_rollup_rows_written'] += $rows;
                }
                $cursor['legacy_rollup_date'] = $nextDate;
                if (! $complete) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                $phase = 'legacy_backfill';
                unset($cursor['legacy_rollup_date']);
                continue;
            }

            if ($phase === 'legacy_backfill') {
                [$complete, $legacyCursor, $requestCount, $eventCount] = $this->copyLegacyBatch(
                    is_array($cursor['legacy_cursor'] ?? null) ? $cursor['legacy_cursor'] : null,
                    $batchSize,
                );
                $stats['legacy_requests_copied'] += $requestCount;
                $stats['legacy_events_copied'] += $eventCount;
                $cursor['legacy_cursor'] = $legacyCursor;
                if (! $complete) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                $phase = 'spool_replay';
                unset($cursor['legacy_cursor']);
                continue;
            }

            if ($phase === 'spool_replay') {
                $replay = $this->spool->replayBatch($batchSize);
                $stats['spool_requests_replayed'] += $replay['processed'];
                $stats['spool_requests_quarantined'] += $replay['quarantined'];
                $stats['spool_replay_failures'] += $replay['failed'];
                if ($replay['retryable_failed'] > 0) {
                    throw new RuntimeException('Replay spool aktivitas berhenti karena terdapat payload yang tidak dapat diproses.');
                }
                if ($replay['remaining'] > 0) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                $phase = 'daily_rollup';
                continue;
            }

            if ($phase === 'daily_rollup') {
                [$complete, $nextDate, $rows] = $this->rollupDay(
                    isset($cursor['rollup_date']) ? (string) $cursor['rollup_date'] : null,
                );
                if ($rows !== null) {
                    $stats['rollup_days_rebuilt']++;
                    $stats['rollup_rows_written'] += $rows;
                }
                $cursor['rollup_date'] = $nextDate;
                if (! $complete) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                $phase = 'verify_requests';
                unset($cursor['rollup_date']);
                continue;
            }

            if ($phase === 'verify_requests') {
                if (! Schema::hasTable('aktivitas')) {
                    $stats['legacy_validation_status'] = 'not_applicable';
                    $stats['legacy_validated_at'] = now('UTC')->toIso8601String();
                    $stats['legacy_cutover_guidance'] = 'Tabel legacy tidak ditemukan; tidak ada rename atau drop otomatis.';

                    return $this->result(false, 'verification_finalize', $cursor, $stats);
                }
                [$complete, $verifyCursor, $checked, $missing, $eventMismatches] = $this->verifyLegacyRequestBatch(
                    is_array($cursor['verify_request_cursor'] ?? null) ? $cursor['verify_request_cursor'] : null,
                    $batchSize,
                );
                $stats['validation_legacy_requests_checked'] += $checked;
                $stats['validation_missing_requests'] += $missing;
                $stats['validation_event_mismatches'] += $eventMismatches;
                $cursor['verify_request_cursor'] = $verifyCursor;
                if (! $complete) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                unset($cursor['verify_request_cursor']);

                return $this->result(false, 'verify_rollups', $cursor, $stats);
            }

            if ($phase === 'verify_rollups') {
                [$complete, $verifyCursor, $daysChecked, $missingDays, $metricMismatches] = $this->verifyLegacyRollupBatch(
                    is_array($cursor['verify_rollup_cursor'] ?? null) ? $cursor['verify_rollup_cursor'] : null,
                    $batchSize,
                );
                $stats['validation_rollup_days_checked'] += $daysChecked;
                $stats['validation_missing_rollup_days'] += $missingDays;
                $stats['validation_rollup_metric_mismatches'] += $metricMismatches;
                $cursor['verify_rollup_cursor'] = $verifyCursor;
                if (! $complete) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                unset($cursor['verify_rollup_cursor']);
                if (! $this->legacyRollupTotalsPreserved()) {
                    $stats['validation_rollup_metric_mismatches']++;
                }
                $valid = $stats['validation_missing_requests'] === 0
                    && $stats['validation_event_mismatches'] === 0
                    && $stats['validation_missing_rollup_days'] === 0
                    && $stats['validation_rollup_metric_mismatches'] === 0;
                $stats['legacy_validation_status'] = $valid ? 'passed' : 'failed';
                $stats['legacy_validated_at'] = now('UTC')->toIso8601String();
                $stats['legacy_cutover_guidance'] = $valid
                    ? 'Validasi lulus. Tabel aktivitas tidak diubah otomatis; rename menjadi tabel rollback hanya setelah snapshot cocok, lalu pertahankan minimal tujuh hari.'
                    : 'Validasi gagal. Jangan rename atau drop tabel aktivitas; perbaiki selisih dan jalankan ulang maintenance.';

                return $this->result(false, 'verification_finalize', $cursor, $stats);
            }

            if ($phase === 'verification_finalize') {
                if (($stats['legacy_validation_status'] ?? 'failed') === 'failed') {
                    throw new RuntimeException(sprintf(
                        'Validasi cutover aktivitas gagal: %d request hilang, %d jumlah event berbeda, %d hari rollup hilang, %d statistik rollup berbeda.',
                        $stats['validation_missing_requests'],
                        $stats['validation_event_mismatches'],
                        $stats['validation_missing_rollup_days'],
                        $stats['validation_rollup_metric_mismatches'],
                    ));
                }
                $phase = 'prune_events';
                continue;
            }

            if ($phase === 'prune_events') {
                if (! in_array(($stats['legacy_validation_status'] ?? 'pending'), ['passed', 'not_applicable'], true)) {
                    $phase = 'verify_requests';
                    continue;
                }
                $deleted = $this->pruneByIds('audit_events', 'occurred_at', $batchSize);
                $stats['events_pruned'] += $deleted;
                if ($deleted === $batchSize) {
                    return $this->result(false, $phase, $cursor, $stats);
                }
                $phase = 'prune_requests';
                continue;
            }

            if ($phase === 'prune_requests') {
                if (! in_array(($stats['legacy_validation_status'] ?? 'pending'), ['passed', 'not_applicable'], true)) {
                    $phase = 'verify_requests';
                    continue;
                }
                $deleted = $this->pruneByIds('request_activities', 'started_at', $batchSize);
                $stats['requests_pruned'] += $deleted;
                if ($deleted === $batchSize) {
                    return $this->result(false, $phase, $cursor, $stats);
                }

                return $this->result(true, 'completed', [], $stats);
            }

            throw new RuntimeException('Cursor maintenance aktivitas tidak dikenal: '.$phase);
        }

        return $this->result(false, $phase, $cursor, $stats);
    }

    /**
     * @param array{started_at:string,id:string}|null $cursor
     * @return array{bool,?array{started_at:string,id:string},int,int}
     */
    private function copyLegacyBatch(?array $cursor, int $limit): array
    {
        if (! Schema::hasTable('aktivitas')) {
            return [true, null, 0, 0];
        }

        $query = DB::table('aktivitas')->where('started_at', '>=', $this->cutoff());
        if ($cursor !== null) {
            $query->where(function (Builder $nested) use ($cursor): void {
                $nested->where('started_at', '>', $cursor['started_at'])
                    ->orWhere(function (Builder $sameTime) use ($cursor): void {
                        $sameTime->where('started_at', $cursor['started_at'])
                            ->where('id', '>', $cursor['id']);
                    });
            });
        }

        $rows = $query->orderBy('started_at')->orderBy('id')->limit($limit)->get();
        if ($rows->isEmpty()) {
            return [true, $cursor, 0, 0];
        }

        $requests = [];
        $events = [];
        foreach ($rows as $row) {
            $entries = $this->legacyEvents($row->event_entries ?? null);
            $requests[] = $this->legacyRequestRow($row, count($entries));
            foreach ($entries as $index => $entry) {
                $events[] = $this->legacyEventRow((string) $row->id, $entry, $index);
            }
        }

        DB::transaction(function () use ($requests, $events): void {
            foreach (array_chunk($requests, 100) as $chunk) {
                DB::table('request_activities')->insertOrIgnore($chunk);
            }
            foreach (array_chunk($events, 250) as $chunk) {
                DB::table('audit_events')->insertOrIgnore($chunk);
            }
        }, 3);

        $last = $rows->last();
        $next = [
            'started_at' => (string) $last->started_at,
            'id' => (string) $last->id,
        ];

        return [$rows->count() < $limit, $next, $rows->count(), count($events)];
    }

    /** @return array{bool,?string,?int} */
    private function rollupDay(?string $date, string $sourceTable = 'request_activities'): array
    {
        if (! in_array($sourceTable, ['request_activities', 'aktivitas'], true)) {
            throw new RuntimeException('Sumber rollup aktivitas tidak valid.');
        }
        $timezone = new \DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
        $lastCompleteDay = CarbonImmutable::now($timezone)->startOfDay()->subDay();
        if ($date === null || $date === '') {
            $minimum = DB::table($sourceTable)
                ->where('is_page_view', true)
                ->whereNotNull('occurred_at')
                ->min('occurred_at');
            if ($minimum === null) {
                return [true, null, null];
            }
            $day = CarbonImmutable::parse((string) $minimum, 'UTC')->setTimezone($timezone)->startOfDay();
        } else {
            $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        }

        if ($day->greaterThan($lastCompleteDay)) {
            return [true, null, null];
        }

        $base = DB::table($sourceTable)
            ->where('is_page_view', true)
            ->whereIn('segment', ['publik', 'login'])
            ->where('occurred_at', '>=', $day->utc())
            ->where('occurred_at', '<', $day->addDay()->utc());
        $rows = (clone $base)
            ->selectRaw("COALESCE(segment, '') AS segment")
            ->selectRaw("COALESCE(route_name, '') AS route_name")
            ->selectRaw("COALESCE(path, '') AS path")
            ->selectRaw("COALESCE(language_code, '') AS language_code")
            ->selectRaw("COALESCE(device_type, '') AS device_type")
            ->selectRaw('COUNT(*) AS page_views')
            ->selectRaw('SUM(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN 1 ELSE 0 END) AS human_page_views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->selectRaw('COUNT(DISTINCT CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN visitor_hash ELSE NULL END) AS human_unique_visitors')
            ->selectRaw('SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS bot_views')
            ->selectRaw('SUM(CASE WHEN is_prefetch = 1 THEN 1 ELSE 0 END) AS prefetch_views')
            ->selectRaw('COALESCE(SUM(response_ms), 0) AS total_response_ms')
            ->selectRaw('COALESCE(AVG(response_ms), 0) AS average_response_ms')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN response_ms ELSE 0 END), 0) AS human_total_response_ms')
            ->selectRaw('COALESCE(AVG(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN response_ms ELSE NULL END), 0) AS human_average_response_ms')
            ->groupByRaw("COALESCE(segment, ''), COALESCE(route_name, ''), COALESCE(path, ''), COALESCE(language_code, ''), COALESCE(device_type, '')")
            ->get();

        $globalSummary = $this->rollupMetrics(clone $base)->first();
        $segmentSummaries = $this->rollupMetrics(
            (clone $base)->selectRaw("COALESCE(segment, '') AS segment"),
        )->groupByRaw("COALESCE(segment, '')")->get();

        $now = now('UTC');
        $inserts = $rows->map(function (object $row) use ($day, $now): array {
            $dimensions = [
                (string) $row->segment,
                (string) $row->route_name,
                substr((string) $row->path, 0, 512),
                (string) $row->language_code,
                (string) $row->device_type,
            ];

            return [
                'activity_date' => $day->format('Y-m-d'),
                'dimension_hash' => hash('sha256', 'detail'."\x1f".$day->format('Y-m-d')."\x1f".implode("\x1f", $dimensions)),
                'rollup_scope' => 'detail',
                'segment' => $dimensions[0],
                'route_name' => $dimensions[1],
                'path' => $dimensions[2],
                'language_code' => $dimensions[3],
                'device_type' => $dimensions[4],
                'page_views' => (int) $row->page_views,
                'human_page_views' => (int) $row->human_page_views,
                'unique_visitors' => (int) $row->unique_visitors,
                'human_unique_visitors' => (int) $row->human_unique_visitors,
                'bot_views' => (int) $row->bot_views,
                'prefetch_views' => (int) $row->prefetch_views,
                'total_response_ms' => (float) $row->total_response_ms,
                'average_response_ms' => (float) $row->average_response_ms,
                'human_total_response_ms' => (float) $row->human_total_response_ms,
                'human_average_response_ms' => (float) $row->human_average_response_ms,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if (is_object($globalSummary)) {
            $inserts[] = $this->summaryRollupRow($day, 'summary', '', $globalSummary, $now);
        }
        foreach ($segmentSummaries as $segmentSummary) {
            $inserts[] = $this->summaryRollupRow(
                $day,
                'segment_summary',
                (string) $segmentSummary->segment,
                $segmentSummary,
                $now,
            );
        }

        DB::transaction(function () use ($day, $inserts): void {
            DB::table('website_daily_rollups')->where('activity_date', $day->format('Y-m-d'))->delete();
            foreach (array_chunk($inserts, 250) as $chunk) {
                DB::table('website_daily_rollups')->insert($chunk);
            }
        }, 3);

        $nextOccurredAt = DB::table($sourceTable)
            ->where('is_page_view', true)
            ->whereNotNull('occurred_at')
            ->where('occurred_at', '>=', $day->addDay()->utc())
            ->min('occurred_at');
        if ($nextOccurredAt === null) {
            return [true, null, count($inserts)];
        }
        $next = CarbonImmutable::parse((string) $nextOccurredAt, 'UTC')->setTimezone($timezone)->startOfDay();

        return [$next->greaterThan($lastCompleteDay), $next->format('Y-m-d'), count($inserts)];
    }

    private function pruneByIds(string $table, string $column, int $limit): int
    {
        $ids = DB::table($table)
            ->where($column, '<', $this->cutoff())
            ->orderBy($column)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids->all())->delete();
    }

    /**
     * @param array{started_at:string,id:string}|null $cursor
     * @return array{bool,?array{started_at:string,id:string},int,int,int}
     */
    private function verifyLegacyRequestBatch(?array $cursor, int $limit): array
    {
        $query = DB::table('aktivitas')
            ->where('started_at', '>=', $this->cutoff());
        if ($cursor !== null) {
            $query->where(function (Builder $nested) use ($cursor): void {
                $nested->where('started_at', '>', $cursor['started_at'])
                    ->orWhere(function (Builder $sameTime) use ($cursor): void {
                        $sameTime->where('started_at', $cursor['started_at'])
                            ->where('id', '>', $cursor['id']);
                    });
            });
        }

        $rows = $query
            ->select(['id', 'started_at', 'event_entries'])
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
        if ($rows->isEmpty()) {
            return [true, $cursor, 0, 0, 0];
        }

        $ids = $rows->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $requests = DB::table('request_activities')
            ->whereIn('id', $ids)
            ->select(['id', 'events_count'])
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->id);
        $eventCounts = DB::table('audit_events')
            ->whereIn('request_id', $ids)
            ->selectRaw('request_id, COUNT(*) AS aggregate')
            ->groupBy('request_id')
            ->pluck('aggregate', 'request_id');

        $missing = 0;
        $eventMismatches = 0;
        foreach ($rows as $row) {
            $id = (string) $row->id;
            $copied = $requests->get($id);
            if (! is_object($copied)) {
                $missing++;
                continue;
            }
            $expected = count($this->legacyEvents($row->event_entries ?? null));
            if ((int) ($copied->events_count ?? 0) !== $expected || (int) ($eventCounts[$id] ?? 0) !== $expected) {
                $eventMismatches++;
            }
        }

        $last = $rows->last();
        $next = ['started_at' => (string) $last->started_at, 'id' => (string) $last->id];

        return [$rows->count() < max(1, $limit), $next, $rows->count(), $missing, $eventMismatches];
    }

    /**
     * @param array{occurred_at:string,id:string,last_date?:string}|null $cursor
     * @return array{bool,?array{occurred_at:string,id:string,last_date:string},int,int,int}
     */
    private function verifyLegacyRollupBatch(?array $cursor, int $limit): array
    {
        $timezone = new \DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
        $completeBefore = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $query = DB::table('aktivitas')
            ->where('is_page_view', true)
            ->whereNotNull('occurred_at')
            ->where('occurred_at', '<', $completeBefore);
        if ($cursor !== null) {
            $query->where(function (Builder $nested) use ($cursor): void {
                $nested->where('occurred_at', '>', $cursor['occurred_at'])
                    ->orWhere(function (Builder $sameTime) use ($cursor): void {
                        $sameTime->where('occurred_at', $cursor['occurred_at'])
                            ->where('id', '>', $cursor['id']);
                    });
            });
        }

        $rows = $query
            ->select(['id', 'occurred_at'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
        if ($rows->isEmpty()) {
            return [true, $cursor, 0, 0, 0];
        }

        $lastCheckedDate = (string) ($cursor['last_date'] ?? '');
        $dates = [];
        foreach ($rows as $row) {
            $date = CarbonImmutable::parse((string) $row->occurred_at, 'UTC')->setTimezone($timezone)->format('Y-m-d');
            if ($date !== $lastCheckedDate) {
                $dates[$date] = true;
                $lastCheckedDate = $date;
            }
        }
        $dateList = array_keys($dates);
        $existing = $dateList === [] ? [] : DB::table('website_daily_rollups')
            ->whereIn('activity_date', $dateList)
            ->where('rollup_scope', 'summary')
            ->pluck('activity_date')
            ->map(static fn (mixed $date): string => substr((string) $date, 0, 10))
            ->all();
        $missingDays = count(array_diff($dateList, $existing));

        $last = $rows->last();
        $next = [
            'occurred_at' => (string) $last->occurred_at,
            'id' => (string) $last->id,
            'last_date' => $lastCheckedDate,
        ];

        return [$rows->count() < max(1, $limit), $next, count($dateList), $missingDays, 0];
    }

    private function legacyRollupTotalsPreserved(): bool
    {
        $timezone = new \DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
        $completeBefore = CarbonImmutable::now($timezone)->startOfDay();
        $expected = $this->rollupMetrics(
            DB::table('aktivitas')
                ->where('is_page_view', true)
                ->whereIn('segment', ['publik', 'login'])
                ->whereNotNull('occurred_at')
                ->where('occurred_at', '<', $completeBefore->utc()),
        )->first();
        $actual = DB::table('website_daily_rollups')
            ->where('activity_date', '<', $completeBefore->format('Y-m-d'))
            ->where('rollup_scope', 'summary')
            ->selectRaw('COALESCE(SUM(page_views), 0) AS page_views')
            ->selectRaw('COALESCE(SUM(human_page_views), 0) AS human_page_views')
            ->selectRaw('COALESCE(SUM(bot_views), 0) AS bot_views')
            ->selectRaw('COALESCE(SUM(prefetch_views), 0) AS prefetch_views')
            ->selectRaw('COALESCE(SUM(total_response_ms), 0) AS total_response_ms')
            ->selectRaw('COALESCE(SUM(human_total_response_ms), 0) AS human_total_response_ms')
            ->first();
        if (! is_object($expected) || ! is_object($actual)) {
            return false;
        }

        foreach ([
            'page_views', 'human_page_views', 'bot_views', 'prefetch_views',
        ] as $field) {
            if ((int) ($actual->{$field} ?? 0) < (int) ($expected->{$field} ?? 0)) {
                return false;
            }
        }
        foreach ([
            'total_response_ms', 'human_total_response_ms',
        ] as $field) {
            if ((float) ($actual->{$field} ?? 0) + 0.01 < (float) ($expected->{$field} ?? 0)) {
                return false;
            }
        }

        return true;
    }

    private function rollupMetrics(Builder $query): Builder
    {
        return $query
            ->selectRaw('COUNT(*) AS page_views')
            ->selectRaw('SUM(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN 1 ELSE 0 END) AS human_page_views')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->selectRaw('COUNT(DISTINCT CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN visitor_hash ELSE NULL END) AS human_unique_visitors')
            ->selectRaw('SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS bot_views')
            ->selectRaw('SUM(CASE WHEN is_prefetch = 1 THEN 1 ELSE 0 END) AS prefetch_views')
            ->selectRaw('COALESCE(SUM(response_ms), 0) AS total_response_ms')
            ->selectRaw('COALESCE(AVG(response_ms), 0) AS average_response_ms')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN response_ms ELSE 0 END), 0) AS human_total_response_ms')
            ->selectRaw('COALESCE(AVG(CASE WHEN is_bot = 0 AND is_prefetch = 0 THEN response_ms ELSE NULL END), 0) AS human_average_response_ms');
    }

    /** @return array<string, mixed> */
    private function summaryRollupRow(
        CarbonImmutable $day,
        string $scope,
        string $segment,
        object $row,
        mixed $now,
    ): array {
        return [
            'activity_date' => $day->format('Y-m-d'),
            'dimension_hash' => hash('sha256', $scope."\x1f".$day->format('Y-m-d')."\x1f".$segment),
            'rollup_scope' => $scope,
            'segment' => $segment,
            'route_name' => '',
            'path' => '',
            'language_code' => '',
            'device_type' => '',
            'page_views' => (int) $row->page_views,
            'human_page_views' => (int) $row->human_page_views,
            'unique_visitors' => (int) $row->unique_visitors,
            'human_unique_visitors' => (int) $row->human_unique_visitors,
            'bot_views' => (int) $row->bot_views,
            'prefetch_views' => (int) $row->prefetch_views,
            'total_response_ms' => (float) $row->total_response_ms,
            'average_response_ms' => (float) $row->average_response_ms,
            'human_total_response_ms' => (float) $row->human_total_response_ms,
            'human_average_response_ms' => (float) $row->human_average_response_ms,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return array<string, mixed> */
    private function legacyRequestRow(object $row, int $eventsCount): array
    {
        $fields = [
            'id', 'actor_type', 'user_id', 'username', 'role', 'branch_id', 'branch_label',
            'impersonator_user_id', 'impersonator_username', 'impersonator_role', 'visitor_hash',
            'ip_address', 'method', 'route_name', 'path', 'category', 'action', 'subject_type',
            'subject_id', 'query_data', 'input_data', 'http_status', 'outcome', 'redirect_to',
            'response_content_type', 'response_size', 'duration_ms', 'user_agent', 'referer',
            'error_type', 'error_message', 'is_page_view', 'identity_source', 'segment',
            'referer_host', 'language_code', 'language_name', 'device_type', 'browser_name',
            'os_name', 'is_bot', 'is_prefetch', 'response_ms', 'occurred_at', 'started_at',
            'completed_at',
        ];
        $source = (array) $row;
        $mapped = [];
        foreach ($fields as $field) {
            $mapped[$field] = $source[$field] ?? null;
        }
        $mapped['actor_type'] = $mapped['actor_type'] ?: 'anonymous';
        $mapped['method'] = $mapped['method'] ?: 'GET';
        $mapped['path'] = $mapped['path'] ?: '/';
        $mapped['category'] = $mapped['category'] ?: 'request';
        $mapped['action'] = $mapped['action'] ?: 'request';
        $mapped['outcome'] = $mapped['outcome'] ?: 'succeeded';
        $mapped['is_page_view'] = (bool) $mapped['is_page_view'];
        $mapped['is_bot'] = (bool) $mapped['is_bot'];
        $mapped['is_prefetch'] = (bool) $mapped['is_prefetch'];
        $mapped['events_count'] = $eventsCount;

        return $mapped;
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyEvents(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value)
            ? array_values(array_filter($value, 'is_array'))
            : [];
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function legacyEventRow(string $requestId, array $entry, int $index): array
    {
        $json = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: null;
        };

        return [
            'id' => substr(hash('sha256', 'legacy-event:'.$requestId.':'.$index), 0, 26),
            'request_id' => $requestId,
            'category' => trim((string) ($entry['category'] ?? 'data')) ?: 'data',
            'action' => trim((string) ($entry['action'] ?? 'changed')) ?: 'changed',
            'subject_type' => $entry['subject_type'] ?? null,
            'subject_id' => isset($entry['subject_id']) ? (string) $entry['subject_id'] : null,
            'subject_label' => $entry['subject_label'] ?? null,
            'description' => $entry['description'] ?? null,
            'before_values' => $json($entry['before_values'] ?? null),
            'after_values' => $json($entry['after_values'] ?? null),
            'changed_values' => $json($entry['changed_values'] ?? null),
            'metadata' => $json($entry['metadata'] ?? null),
            'occurred_at' => $entry['occurred_at'] ?? now('UTC'),
        ];
    }

    /** @return array{complete:bool,cursor:array<string, mixed>,summary:array<string, mixed>} */
    private function result(bool $complete, string $phase, array $cursor, array $stats): array
    {
        $cursor['phase'] = $phase;
        $cursor['stats'] = $stats;

        return ['complete' => $complete, 'cursor' => $cursor, 'summary' => $stats];
    }

    private function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->subDays((int) config('activity.retention_days', 90));
    }

    private function safeCount(string $table, ?string $dateColumn = null, ?CarbonImmutable $before = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($dateColumn !== null && $before !== null) {
            $query->where($dateColumn, '<', $before);
        }

        return $query->count();
    }
}

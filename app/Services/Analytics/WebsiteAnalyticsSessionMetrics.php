<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebsiteAnalyticsSessionMetrics
{
    public function count(Builder $query): int
    {
        $thresholdSeconds = max(1, (int) config('analytics.session_inactivity_minutes', 30)) * 60;

        try {
            $rows = (clone $query)
                ->select(['id', 'visitor_hash', 'occurred_at'])
                ->selectRaw('LAG(occurred_at) OVER (PARTITION BY visitor_hash ORDER BY occurred_at, id) AS previous_at')
                ->reorder('visitor_hash')
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->toBase();
            $driver = $query->getModel()->getConnection()->getDriverName();
            $gapExpression = match ($driver) {
                'sqlite' => "CAST(strftime('%s', occurred_at) AS INTEGER) - CAST(strftime('%s', previous_at) AS INTEGER)",
                'pgsql' => 'EXTRACT(EPOCH FROM (occurred_at - previous_at))',
                default => 'TIMESTAMPDIFF(SECOND, previous_at, occurred_at)',
            };
            $result = DB::query()
                ->fromSub($rows, 'session_rows')
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN visitor_hash IS NULL OR visitor_hash = '' OR previous_at IS NULL OR {$gapExpression} > ? THEN 1 ELSE 0 END), 0) AS session_count",
                    [$thresholdSeconds],
                )
                ->first();

            return max(0, (int) ($result->session_count ?? 0));
        } catch (Throwable) {
            return $this->streamCount($query, $thresholdSeconds);
        }
    }

    /** @return array<string, int> */
    public function countsByVisitor(Builder $query): array
    {
        $thresholdSeconds = max(1, (int) config('analytics.session_inactivity_minutes', 30)) * 60;
        $counts = [];
        $previousVisitor = null;
        $previousAt = null;

        foreach ($this->sessionRows($query) as $row) {
            $visitorHash = trim((string) ($row->visitor_hash ?? ''));
            if ($visitorHash === '') {
                $counts[''] = ($counts[''] ?? 0) + 1;
                $previousVisitor = null;
                $previousAt = null;

                continue;
            }

            $occurredAt = $this->occurredAt($row->occurred_at ?? null);
            $isNewSession = $previousVisitor !== $visitorHash
                || ! $previousAt instanceof CarbonImmutable
                || ! $occurredAt instanceof CarbonImmutable
                || $occurredAt->greaterThan($previousAt->addSeconds($thresholdSeconds));

            if ($isNewSession) {
                $counts[$visitorHash] = ($counts[$visitorHash] ?? 0) + 1;
            }

            $previousVisitor = $visitorHash;
            $previousAt = $occurredAt;
        }

        return $counts;
    }

    private function sessionRows(Builder $query): iterable
    {
        return (clone $query)
            ->select(['id', 'visitor_hash', 'occurred_at'])
            ->reorder('visitor_hash')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->cursor();
    }

    private function occurredAt(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return CarbonImmutable::parse($raw, 'UTC');
    }

    private function streamCount(Builder $query, int $thresholdSeconds): int
    {
        $count = 0;
        $previousVisitor = null;
        $previousAt = null;
        foreach ($this->sessionRows($query) as $row) {
            $visitorHash = trim((string) ($row->visitor_hash ?? ''));
            $occurredAt = $this->occurredAt($row->occurred_at ?? null);
            if ($visitorHash === ''
                || $previousVisitor !== $visitorHash
                || ! $previousAt instanceof CarbonImmutable
                || ! $occurredAt instanceof CarbonImmutable
                || $occurredAt->greaterThan($previousAt->addSeconds($thresholdSeconds))) {
                $count++;
            }
            $previousVisitor = $visitorHash !== '' ? $visitorHash : null;
            $previousAt = $visitorHash !== '' ? $occurredAt : null;
        }

        return $count;
    }
}

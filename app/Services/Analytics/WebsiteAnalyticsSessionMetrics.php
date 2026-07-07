<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class WebsiteAnalyticsSessionMetrics
{
    public function count(Builder $query): int
    {
        return (int) array_sum($this->countsByVisitor($query));
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
}

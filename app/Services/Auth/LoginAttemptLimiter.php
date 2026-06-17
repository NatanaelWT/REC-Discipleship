<?php

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use Carbon\CarbonImmutable;

class LoginAttemptLimiter
{
    private const WINDOW_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 900;
    private const RETENTION_SECONDS = 172800;

    public function waitSeconds(string $ip, CarbonImmutable $now): int
    {
        $this->prune($now);

        $attempt = $this->find($ip);
        if (! $attempt instanceof LoginAttempt || $attempt->locked_until_at === null) {
            return 0;
        }

        if ($attempt->locked_until_at->lessThanOrEqualTo($now)) {
            return 0;
        }

        return max(0, (int) $now->diffInSeconds($attempt->locked_until_at, false));
    }

    public function clear(string $ip): void
    {
        LoginAttempt::query()->where('attempt_key', $this->key($ip))->delete();
    }

    public function registerFailure(string $ip, CarbonImmutable $now): int
    {
        $this->prune($now);

        $attempt = $this->find($ip);
        $count = max(0, (int) ($attempt?->failed_attempt_count ?? 0));
        $windowStartedAt = $attempt?->window_started_at !== null
            ? CarbonImmutable::parse($attempt->window_started_at)
            : null;
        $lockedUntilAt = $attempt?->locked_until_at !== null
            ? CarbonImmutable::parse($attempt->locked_until_at)
            : null;

        if ($lockedUntilAt !== null && $lockedUntilAt->greaterThan($now)) {
            $attempt->forceFill(['last_attempted_at' => $now])->save();

            return max(0, (int) $now->diffInSeconds($lockedUntilAt, false));
        }

        if ($windowStartedAt === null || $now->diffInSeconds($windowStartedAt, true) > self::WINDOW_SECONDS) {
            $count = 0;
            $windowStartedAt = $now;
        }

        $count++;
        if ($count >= self::MAX_ATTEMPTS) {
            $lockedUntilAt = $now->addSeconds(self::LOCK_SECONDS);
            $count = 0;
            $windowStartedAt = $now;
        } else {
            $lockedUntilAt = null;
        }

        LoginAttempt::query()->updateOrCreate(
            ['attempt_key' => $this->key($ip)],
            [
                'failed_attempt_count' => $count,
                'window_started_at' => $windowStartedAt,
                'locked_until_at' => $lockedUntilAt,
                'last_attempted_at' => $now,
            ],
        );

        return $lockedUntilAt !== null ? max(0, (int) $now->diffInSeconds($lockedUntilAt, false)) : 0;
    }

    private function find(string $ip): ?LoginAttempt
    {
        return LoginAttempt::query()->where('attempt_key', $this->key($ip))->first();
    }

    private function prune(CarbonImmutable $now): void
    {
        LoginAttempt::query()->get()->each(static function (LoginAttempt $attempt) use ($now): void {
            $references = array_filter([
                $attempt->window_started_at,
                $attempt->locked_until_at,
                $attempt->last_attempted_at,
            ]);

            if ($references === []) {
                $attempt->delete();

                return;
            }

            $latest = collect($references)->map(static fn ($date) => CarbonImmutable::parse($date))->max();
            if ($latest instanceof CarbonImmutable && $now->diffInSeconds($latest, true) > self::RETENTION_SECONDS) {
                $attempt->delete();
            }
        });
    }

    private function key(string $ip): string
    {
        return hash('sha256', $ip);
    }
}

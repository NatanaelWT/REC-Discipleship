<?php

function read_login_attempts(): array {
    if (
        ! class_exists(\App\Models\LoginAttempt::class)
        || ! \Illuminate\Support\Facades\Schema::hasTable('login_attempts')
    ) {
        return [];
    }

    $attempts = [];
    \App\Models\LoginAttempt::query()->get()->each(static function (\App\Models\LoginAttempt $attempt) use (&$attempts): void {
        $key = (string) $attempt->attempt_key;
        if ($key === '') {
            return;
        }

        $attempts[$key] = [
            'count' => max(0, (int) $attempt->failed_attempt_count),
            'window_start' => $attempt->window_started_at?->getTimestamp() ?? 0,
            'lock_until' => $attempt->locked_until_at?->getTimestamp() ?? 0,
            'last' => $attempt->last_attempted_at?->getTimestamp() ?? 0,
        ];
    });

    return $attempts;
}

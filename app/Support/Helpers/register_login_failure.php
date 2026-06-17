<?php

function register_login_failure(array &$attempts, string $ip, int $now): int {
    $windowSeconds = 10 * 60;
    $maxAttempts = 5;
    $lockSeconds = 15 * 60;
    $key = login_attempt_key($ip);
    $row = isset($attempts[$key]) && is_array($attempts[$key]) ? $attempts[$key] : [];

    $count = max(0, (int) ($row['count'] ?? 0));
    $windowStart = max(0, (int) ($row['window_start'] ?? 0));
    $lockUntil = max(0, (int) ($row['lock_until'] ?? 0));

    if ($lockUntil > $now) {
        $row['last'] = $now;
        $attempts[$key] = $row;
        return $lockUntil - $now;
    }

    if ($windowStart <= 0 || ($now - $windowStart) > $windowSeconds) {
        $count = 0;
        $windowStart = $now;
    }

    $count++;
    if ($count >= $maxAttempts) {
        $lockUntil = $now + $lockSeconds;
        $count = 0;
        $windowStart = $now;
    } else {
        $lockUntil = 0;
    }

    $attempts[$key] = [
        'count' => $count,
        'window_start' => $windowStart,
        'lock_until' => $lockUntil,
        'last' => $now,
    ];

    if ($lockUntil > $now) {
        return $lockUntil - $now;
    }
    return 0;
}

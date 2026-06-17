<?php

function login_wait_seconds(array $attempts, string $ip, int $now): int {
    $key = login_attempt_key($ip);
    if (!isset($attempts[$key]) || !is_array($attempts[$key])) {
        return 0;
    }
    $lockUntil = max(0, (int) ($attempts[$key]['lock_until'] ?? 0));
    if ($lockUntil <= $now) {
        return 0;
    }
    return $lockUntil - $now;
}

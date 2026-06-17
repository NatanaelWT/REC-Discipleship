<?php

function prune_login_attempts(array $attempts, int $now): array {
    $retentionSeconds = 2 * 24 * 60 * 60;
    $pruned = [];
    foreach ($attempts as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $count = max(0, (int) ($row['count'] ?? 0));
        $windowStart = max(0, (int) ($row['window_start'] ?? 0));
        $lockUntil = max(0, (int) ($row['lock_until'] ?? 0));
        $last = max(0, (int) ($row['last'] ?? 0));
        $reference = max($windowStart, $lockUntil, $last);
        if ($reference > 0 && ($now - $reference) > $retentionSeconds) {
            continue;
        }
        $pruned[(string) $key] = [
            'count' => $count,
            'window_start' => $windowStart,
            'lock_until' => $lockUntil,
            'last' => $last,
        ];
    }
    return $pruned;
}

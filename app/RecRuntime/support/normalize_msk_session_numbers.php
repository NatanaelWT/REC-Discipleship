<?php

function normalize_msk_session_numbers($value): array {
    if (!is_array($value)) {
        return [];
    }
    $map = [];
    foreach ($value as $sessionNumber) {
        if (!is_numeric($sessionNumber)) {
            continue;
        }
        $session = (int) $sessionNumber;
        if ($session < 1 || $session > 12) {
            continue;
        }
        $map[$session] = true;
    }
    $sessions = array_keys($map);
    sort($sessions, SORT_NUMERIC);
    return array_values($sessions);
}

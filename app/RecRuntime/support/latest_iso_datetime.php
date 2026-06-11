<?php

function latest_iso_datetime(array $values): string {
    $bestValue = '';
    $bestTs = null;
    foreach ($values as $value) {
        $normalized = normalize_iso_datetime_to_jakarta((string) $value);
        if ($normalized === '') {
            continue;
        }
        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            continue;
        }
        if ($bestTs === null || $timestamp > $bestTs) {
            $bestTs = $timestamp;
            $bestValue = $normalized;
        }
    }
    return $bestValue;
}

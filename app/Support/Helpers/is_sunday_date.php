<?php

function is_sunday_date(string $value): bool {
    $normalized = normalize_ymd_date($value);
    if ($normalized === '') {
        return false;
    }
    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return false;
    }
    return date('w', $timestamp) === '0';
}

<?php

function normalize_ymd_date(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }
    return date('Y-m-d', $timestamp);
}

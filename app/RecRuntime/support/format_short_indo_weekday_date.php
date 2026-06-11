<?php

function format_short_indo_weekday_date(string $value): string {
    $normalized = normalize_ymd_date($value);
    if ($normalized === '') {
        return '-';
    }
    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }
    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $dayName = $dayNames[(int) date('w', $timestamp)] ?? '';
    return $dayName . ', ' . format_short_indo_date($normalized);
}

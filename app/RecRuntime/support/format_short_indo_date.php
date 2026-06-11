<?php

function format_short_indo_date(string $value): string {
    $normalized = normalize_ymd_date($value);
    if ($normalized === '') {
        return '-';
    }
    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }
    $monthNames = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];
    $day = (int) date('j', $timestamp);
    $month = $monthNames[(int) date('n', $timestamp)] ?? date('m', $timestamp);
    return $day . ' ' . $month;
}

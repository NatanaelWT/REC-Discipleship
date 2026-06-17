<?php

function format_indo_date(string $value): string {
    $normalized = normalize_ymd_date($value);
    if ($normalized === '') {
        return '-';
    }
    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }
    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $dayName = $dayNames[(int) date('w', $timestamp)] ?? '';
    $day = (int) date('j', $timestamp);
    $monthName = $monthNames[(int) date('n', $timestamp)] ?? date('m', $timestamp);
    $year = date('Y', $timestamp);
    return $dayName . ', ' . $day . ' ' . $monthName . ' ' . $year;
}

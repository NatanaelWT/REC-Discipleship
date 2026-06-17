<?php

function format_indo_month(string $value): string {
    $monthValue = normalize_month_value($value);
    $timestamp = strtotime($monthValue . '-01');
    if ($timestamp === false) {
        return $monthValue;
    }
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
    $month = $monthNames[(int) date('n', $timestamp)] ?? date('m', $timestamp);
    $year = date('Y', $timestamp);
    return $month . ' ' . $year;
}

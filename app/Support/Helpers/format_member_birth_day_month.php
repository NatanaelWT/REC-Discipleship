<?php

function format_member_birth_day_month(string $value): string {
    $normalized = normalize_member_birth_day_month_value($value);
    if ($normalized === '') {
        return '-';
    }

    $day = (int) substr($normalized, 0, 2);
    $month = (int) substr($normalized, 3, 2);
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
    $monthName = $monthNames[$month] ?? sprintf('%02d', $month);
    return $day . ' ' . $monthName;
}

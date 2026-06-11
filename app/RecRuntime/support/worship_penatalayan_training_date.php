<?php

function worship_penatalayan_training_date(string $value, string $monthValue = ''): string {
    $value = preg_replace("/\s+/", ' ', str_replace(["\r", "\n"], ' ', trim($value))) ?? trim($value);
    if ($value === '') {
        return '';
    }

    $dateValue = normalize_ymd_date($value);
    if ($dateValue !== '') {
        return $dateValue;
    }

    $monthMap = [
        'jan' => 1,
        'januari' => 1,
        'feb' => 2,
        'februari' => 2,
        'mar' => 3,
        'maret' => 3,
        'apr' => 4,
        'april' => 4,
        'mei' => 5,
        'jun' => 6,
        'juni' => 6,
        'jul' => 7,
        'juli' => 7,
        'agu' => 8,
        'agt' => 8,
        'agustus' => 8,
        'sep' => 9,
        'sept' => 9,
        'september' => 9,
        'okt' => 10,
        'oktober' => 10,
        'nov' => 11,
        'november' => 11,
        'des' => 12,
        'desember' => 12,
    ];
    $normalizedValue = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    if (preg_match('/^(?:(minggu|senin|selasa|rabu|kamis|jumat|sabtu)\s*,?\s*)?(\d{1,2})\s+([[:alpha:]]+)(?:\s+(\d{4}))?$/u', $normalizedValue, $matches) !== 1) {
        return '';
    }

    $day = (int) ($matches[2] ?? 0);
    $monthKey = trim((string) ($matches[3] ?? ''));
    $monthNumber = $monthMap[$monthKey] ?? 0;
    if ($day < 1 || $monthNumber < 1 || $monthNumber > 12) {
        return '';
    }

    $normalizedMonth = normalize_month_value($monthValue !== '' ? $monthValue : date('Y-m'));
    $scheduleTimestamp = strtotime($normalizedMonth . '-01');
    if ($scheduleTimestamp === false) {
        $scheduleTimestamp = time();
    }
    $scheduleYear = (int) date('Y', $scheduleTimestamp);
    $scheduleMonthNumber = (int) date('n', $scheduleTimestamp);
    $year = isset($matches[4]) && trim((string) $matches[4]) !== ''
        ? (int) $matches[4]
        : ($monthNumber > $scheduleMonthNumber ? $scheduleYear - 1 : $scheduleYear);

    if (!checkdate($monthNumber, $day, $year)) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $year, $monthNumber, $day);
}

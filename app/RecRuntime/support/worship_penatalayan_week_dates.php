<?php

function worship_penatalayan_week_dates(string $monthValue): array {
    $monthValue = normalize_month_value($monthValue);
    $timestamp = strtotime($monthValue . '-01');
    if ($timestamp === false) {
        return [];
    }
    $daysInMonth = (int) date('t', $timestamp);
    $dates = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateValue = sprintf('%s-%02d', $monthValue, $day);
        if (is_sunday_date($dateValue)) {
            $dates[] = $dateValue;
        }
    }
    return $dates;
}

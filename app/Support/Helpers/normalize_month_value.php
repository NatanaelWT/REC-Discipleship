<?php

function normalize_month_value(string $value): string {
    $value = trim($value);
    if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
        return date('Y-m');
    }
    $year = (int) substr($value, 0, 4);
    $month = (int) substr($value, 5, 2);
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        return date('Y-m');
    }
    return sprintf('%04d-%02d', $year, $month);
}

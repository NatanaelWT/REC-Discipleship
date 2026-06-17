<?php

function import_normalize_month_strict(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
        $normalized = normalize_month_value($value);
        return $normalized === $value ? $normalized : '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $ymd = normalize_ymd_date($value);
        if ($ymd === '') {
            return '';
        }
        return substr($ymd, 0, 7);
    }
    return '';
}

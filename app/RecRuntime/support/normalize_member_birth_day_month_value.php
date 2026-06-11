<?php

function normalize_member_birth_day_month_value(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('d-m', $timestamp);
    }

    $value = str_replace(['/', '.', ' '], '-', $value);
    if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $value, $matches) !== 1) {
        return '';
    }

    $day = (int) $matches[1];
    $month = (int) $matches[2];
    if (!checkdate($month, $day, 2000)) {
        return '';
    }

    return sprintf('%02d-%02d', $day, $month);
}

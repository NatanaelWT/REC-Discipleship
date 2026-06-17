<?php

function normalize_church_folder_segment(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = str_replace(['\\', '/'], '-', $value);
    $value = preg_replace('/\s+/', '-', $value) ?? $value;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && trim($converted) !== '') {
            $value = $converted;
        }
    }
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';
    $value = trim($value, '-._');
    if ($value === '' || preg_match('/^[.]+$/', $value) === 1) {
        return '';
    }
    if (strlen($value) > 64) {
        $value = substr($value, 0, 64);
        $value = rtrim($value, '-._');
    }
    if ($value === '' || preg_match('/^[.]+$/', $value) === 1) {
        return '';
    }
    return $value;
}

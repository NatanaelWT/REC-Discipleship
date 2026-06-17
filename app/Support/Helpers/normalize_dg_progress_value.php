<?php

function normalize_dg_progress_value(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^DG\s*([1-3])$/i', $value, $match) === 1) {
        return 'DG ' . $match[1];
    }
    if (preg_match('/^[1-3]$/', $value) === 1) {
        return 'DG ' . $value;
    }
    return '';
}

<?php

function normalize_discipleship_targets($value): array {
    $defaults = default_discipleship_targets();
    if (!is_array($value)) {
        return $defaults;
    }
    $normalized = [];
    foreach ($defaults as $key => $defaultValue) {
        $raw = $value[$key] ?? $defaultValue;
        if (is_string($raw)) {
            $raw = preg_replace('/[^0-9]/', '', $raw) ?? '';
        }
        if (!is_numeric($raw)) {
            $raw = $defaultValue;
        }
        $number = (int) $raw;
        if ($number < 0) {
            $number = 0;
        }
        if ($number > 1000000) {
            $number = 1000000;
        }
        $normalized[$key] = $number;
    }
    return $normalized;
}

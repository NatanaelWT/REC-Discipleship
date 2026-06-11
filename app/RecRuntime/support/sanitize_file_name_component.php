<?php

function sanitize_file_name_component(string $value, string $fallback = 'file'): string {
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && trim($converted) !== '') {
            $value = $converted;
        }
    }
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', strtolower($value));
    if (!is_string($normalized)) {
        $normalized = '';
    }
    $normalized = trim($normalized, '-');
    return $normalized !== '' ? $normalized : $fallback;
}

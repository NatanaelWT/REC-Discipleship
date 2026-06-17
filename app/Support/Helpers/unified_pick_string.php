<?php

function unified_pick_string(array $source, array $fallback, array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return trim((string) $source[$key]);
        }
    }
    foreach ($keys as $key) {
        if (array_key_exists($key, $fallback)) {
            return trim((string) $fallback[$key]);
        }
    }
    return $default;
}

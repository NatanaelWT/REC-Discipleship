<?php

function import_build_header_map(array $headerRow): array {
    $map = [];
    foreach ($headerRow as $idx => $headerValue) {
        $key = strtolower(trim((string) $headerValue));
        $key = preg_replace('/\s+/', '_', $key) ?? $key;
        if ($key === '') {
            continue;
        }
        if (!isset($map[$key])) {
            $map[$key] = (int) $idx;
        }
    }
    return $map;
}

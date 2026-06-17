<?php

function sanitize_relative_upload_path(string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if ($path === '' || strpos($path, '..') !== false) {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
        return '';
    }
    if (strpos($path, ':') !== false) {
        return '';
    }

    $segments = explode('/', $path);
    $cleanSegments = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment[0] === '.') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9._-]+$/', $segment) !== 1) {
            return '';
        }
        $cleanSegments[] = $segment;
    }
    if (count($cleanSegments) === 0) {
        return '';
    }
    return implode('/', $cleanSegments);
}

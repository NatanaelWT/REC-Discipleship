<?php

use App\Services\Routing\AppPageRouteMap;

function secure_upload_url(string $path, bool $download = false, string $downloadName = ''): string
{
    $safePath = sanitize_relative_upload_path($path);
    if ($safePath === '' || ! is_upload_path($safePath)) {
        return '';
    }
    $query = [
        'path' => $safePath,
    ];
    if ($download) {
        $query['download'] = '1';
    }
    $name = trim($downloadName);
    if ($name !== '') {
        $query['name'] = $name;
    }
    try {
        return route('secure-file.show', $query, false);
    } catch (Throwable) {
        return AppPageRouteMap::pageUrl('secure_file', $query);
    }
}

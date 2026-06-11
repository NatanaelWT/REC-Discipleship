<?php

function secure_upload_url(string $path, bool $download = false, string $downloadName = ''): string {
    $safePath = sanitize_relative_upload_path($path);
    if ($safePath === '' || !is_upload_path($safePath)) {
        return '';
    }
    $scriptPath = trim((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = str_replace('\\', '/', $scriptPath);
    if ($scriptPath === '' || substr($scriptPath, -4) !== '.php') {
        $scriptPath = 'index.php';
    } elseif ($scriptPath[0] !== '/') {
        $scriptPath = '/' . ltrim($scriptPath, '/');
    }
    $query = [
        'page' => 'secure_file',
        'path' => $safePath,
    ];
    if ($download) {
        $query['download'] = '1';
    }
    $name = trim($downloadName);
    if ($name !== '') {
        $query['name'] = $name;
    }
    return $scriptPath . '?' . http_build_query($query);
}

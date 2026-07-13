<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

function secure_upload_url(string $path, bool $download = false, string $downloadName = '', bool $raw = false): string
{
    $safePath = sanitize_relative_upload_path($path);
    $viewerId = Auth::id();
    if ($safePath === '' || ! is_upload_path($safePath) || $viewerId === null) {
        return '';
    }
    $query = [
        'path' => $safePath,
        'viewer' => hash_hmac('sha256', implode('|', [
            (string) $viewerId,
            (string) Auth::user()?->username,
            (string) Auth::user()?->access_scope,
            (string) Auth::user()?->branch_id,
        ]), (string) config('app.key')),
    ];
    if ($download) {
        $query['download'] = '1';
    }
    $name = trim($downloadName);
    if ($name !== '') {
        $query['name'] = $name;
    }
    if ($raw) {
        $query['raw'] = '1';
    }
    try {
        return URL::temporarySignedRoute(
            'secure-file.show',
            now()->addMinutes((int) config('media.secure_url_minutes', 30)),
            $query,
            false,
        );
    } catch (Throwable) {
        return '';
    }
}

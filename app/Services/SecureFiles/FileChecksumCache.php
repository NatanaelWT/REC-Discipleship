<?php

namespace App\Services\SecureFiles;

use Illuminate\Support\Facades\Cache;

class FileChecksumCache
{
    public function sha256(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $size = @filesize($path);
        $modifiedAt = @filemtime($path);
        if (! is_int($size) || ! is_int($modifiedAt)) {
            return null;
        }

        $key = 'secure-file:sha256:'.sha1(str_replace('\\', '/', $path).':'.$size.':'.$modifiedAt);

        return Cache::remember($key, now()->addDays(90), static function () use ($path): ?string {
            $hash = @hash_file('sha256', $path);

            return is_string($hash) && $hash !== '' ? $hash : null;
        });
    }
}

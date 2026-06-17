<?php

function resolve_relative_upload_path(string $path): ?string {
    $safePath = sanitize_relative_upload_path($path);
    if ($safePath === '' || !is_upload_path($safePath)) {
        return null;
    }

    $baseRoots = [
        rec_public_path(),
        rec_runtime_path(),
        storage_path('app/public'),
        base_path(),
    ];

    $seen = [];
    foreach ($baseRoots as $baseRoot) {
        $baseRoot = rtrim(str_replace('\\', '/', (string) $baseRoot), '/');
        if ($baseRoot === '' || isset($seen[$baseRoot])) {
            continue;
        }
        $seen[$baseRoot] = true;

        $uploadsRoot = realpath($baseRoot . '/uploads');
        $fullPath = realpath($baseRoot . '/' . $safePath);
        if ($uploadsRoot === false || $fullPath === false || !is_file($fullPath)) {
            continue;
        }

        $uploadsRootNormalized = rtrim(str_replace('\\', '/', $uploadsRoot), '/');
        $fullPathNormalized = str_replace('\\', '/', $fullPath);
        if ($fullPathNormalized === $uploadsRootNormalized || str_starts_with($fullPathNormalized, $uploadsRootNormalized . '/')) {
            return $fullPath;
        }
    }

    return null;
}

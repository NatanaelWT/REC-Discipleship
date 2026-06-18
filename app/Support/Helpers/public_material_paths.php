<?php

function public_material_base_relative_path(): string {
    $base = function_exists('config') ? (string) config('public_materials.base_path', 'msk-dg') : 'msk-dg';
    $base = trim(str_replace('\\', '/', $base), '/');
    if ($base === '' || str_contains($base, '..')) {
        return 'msk-dg';
    }

    return $base;
}

function is_valid_public_material_folder_path(string $folderPath): bool {
    $rawPath = trim(str_replace('\\', '/', $folderPath));
    if ($rawPath === '') {
        return true;
    }

    return normalize_church_folder_path($rawPath) !== '';
}

function public_material_menu_folder_path(string $folderPath): string {
    $folderPath = normalize_church_folder_path($folderPath);
    if ($folderPath === '') {
        return '';
    }

    $segments = explode('/', $folderPath);
    if (count($segments) > 0 && strtolower($segments[0]) === public_material_base_relative_path()) {
        array_shift($segments);
    }

    return implode('/', $segments);
}

function public_material_folder_relative_path(string $folderPath = ''): string {
    $folderPath = public_material_menu_folder_path($folderPath);
    $base = public_material_base_relative_path();

    return $folderPath === '' ? $base : $base . '/' . $folderPath;
}

function public_material_file_relative_path(string $folderPath, string $fileName): string {
    $fileName = basename(str_replace('\\', '/', trim($fileName)));
    if ($fileName === '') {
        return '';
    }

    return sanitize_relative_upload_path(public_material_folder_relative_path($folderPath) . '/' . $fileName);
}

function public_material_folder_full_path(string $folderPath = ''): string {
    return storage_path('app/public/' . public_material_folder_relative_path($folderPath));
}

function public_material_current_relative_path(string $path): string {
    $safePath = sanitize_relative_upload_path($path);
    if ($safePath === '') {
        return '';
    }

    $base = public_material_base_relative_path();
    if ($safePath === $base || str_starts_with($safePath, $base . '/')) {
        return $safePath;
    }

    return '';
}

function is_public_material_path(string $path): bool {
    return public_material_current_relative_path($path) !== '';
}

function public_material_resolve_path(string $path): ?string {
    $safePath = sanitize_relative_upload_path($path);
    if ($safePath === '' || ! is_public_material_path($safePath)) {
        return null;
    }

    $currentPath = public_material_current_relative_path($safePath);
    $publicStoragePath = storage_path('app/public/' . $currentPath);
    if (is_file($publicStoragePath)) {
        return $publicStoragePath;
    }

    return null;
}

function public_material_public_url(string $path): string {
    $currentPath = public_material_current_relative_path($path);
    if ($currentPath === '') {
        return '';
    }

    if (! is_file(storage_path('app/public/' . $currentPath))) {
        return '';
    }

    return asset('storage/' . $currentPath);
}

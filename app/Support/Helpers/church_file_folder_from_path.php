<?php

function church_file_folder_from_path(string $filePath): string {
    $safePath = public_material_current_relative_path($filePath);
    if ($safePath === '') {
        return '';
    }
    $base = church_files_base_relative_path();
    if ($safePath === $base) {
        return '';
    }
    if (strpos($safePath, $base . '/') !== 0) {
        return '';
    }
    $relative = substr($safePath, strlen($base) + 1);
    $folder = trim(str_replace('\\', '/', dirname($relative)), '/');
    if ($folder === '' || $folder === '.') {
        return '';
    }
    return normalize_church_folder_path($folder);
}

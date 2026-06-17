<?php

function church_files_for_public_material(array $churchFiles, string $menu): array {
    $option = public_material_option($menu);
    $targetFolder = normalize_church_folder_path((string) ($option['folder'] ?? ''));
    if ($targetFolder === '') {
        return [];
    }

    $rows = [];
    foreach ($churchFiles as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $path = sanitize_relative_upload_path((string) ($entry['path'] ?? ''));
        if ($path === '') {
            continue;
        }
        $folderPath = church_file_folder_from_path($path);
        if ($folderPath !== $targetFolder && strpos($folderPath, $targetFolder . '/') !== 0) {
            continue;
        }
        $rows[] = $entry;
    }

    usort($rows, function ($a, $b) {
        $aTitle = trim((string) ($a['title'] ?? $a['file_name'] ?? ''));
        $bTitle = trim((string) ($b['title'] ?? $b['file_name'] ?? ''));
        $cmp = strcasecmp($aTitle, $bTitle);
        if ($cmp !== 0) {
            return $cmp;
        }
        $aFileName = trim((string) ($a['file_name'] ?? ''));
        $bFileName = trim((string) ($b['file_name'] ?? ''));
        return strcasecmp($aFileName, $bFileName);
    });

    return $rows;
}

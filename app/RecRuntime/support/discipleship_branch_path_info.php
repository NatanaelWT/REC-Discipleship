<?php

function discipleship_branch_path_info(string $path): ?array {
    $normalizedPath = str_replace('\\', '/', $path);
    $branchMarker = '?branch=';
    $branchMarkerPos = strpos($normalizedPath, $branchMarker);
    if ($branchMarkerPos !== false) {
        $filePath = substr($normalizedPath, 0, $branchMarkerPos);
        $branchRaw = substr($normalizedPath, $branchMarkerPos + strlen($branchMarker));
        $ampPos = strpos($branchRaw, '&');
        if ($ampPos !== false) {
            $branchRaw = substr($branchRaw, 0, $ampPos);
        }
        $branchRaw = strtolower(trim(rawurldecode($branchRaw)));
        if (!is_known_public_branch_code($branchRaw)) {
            return null;
        }
        $dataRoot = str_replace('\\', '/', legacy_runtime_path('data') . '/');
        if (strpos($filePath, $dataRoot) !== 0) {
            return null;
        }
        $relativeFile = substr($filePath, strlen($dataRoot));
        if ($relativeFile === '' || strpos($relativeFile, '/') !== false || substr($relativeFile, -5) !== '.json') {
            return null;
        }
        $name = substr($relativeFile, 0, -5);
        if (!isset(branch_scoped_data_names()[$name])) {
            return null;
        }
        $name = canonical_data_name($name);
        return [
            'branch' => normalize_public_branch_code($branchRaw),
            'name' => $name,
        ];
    }

    $branchRoot = str_replace('\\', '/', legacy_runtime_path('data/cabang') . '/');
    if (strpos($normalizedPath, $branchRoot) !== 0) {
        return null;
    }

    $relativePath = substr($normalizedPath, strlen($branchRoot));
    $parts = explode('/', $relativePath);
    if (count($parts) !== 2) {
        return null;
    }

    $branchRaw = strtolower(trim((string) $parts[0]));
    if (!is_known_public_branch_code($branchRaw)) {
        return null;
    }
    $branch = normalize_public_branch_code($branchRaw);
    $fileName = (string) $parts[1];
    if (substr($fileName, -5) !== '.json') {
        return null;
    }

    $name = substr($fileName, 0, -5);
    if (!isset(branch_scoped_data_names()[$name])) {
        return null;
    }
    $name = canonical_data_name($name);

    return [
        'branch' => $branch,
        'name' => $name,
    ];
}

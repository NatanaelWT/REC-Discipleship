<?php

function read_sheet_format_sidecar(string $csvFullPath, int $maxRows = 200, int $maxCols = 26): array {
    $sidecarPath = sheet_format_sidecar_path($csvFullPath);
    if (!is_file($sidecarPath)) {
        return ['rows' => [], 'cols' => [], 'freeze_rows' => 0];
    }
    $raw = file_get_contents($sidecarPath);
    if ($raw === false || trim($raw) === '') {
        return ['rows' => [], 'cols' => [], 'freeze_rows' => 0];
    }
    $decoded = json_decode($raw, true);
    return normalize_sheet_format_payload($decoded, $maxRows, $maxCols);
}

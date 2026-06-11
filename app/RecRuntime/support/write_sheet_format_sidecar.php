<?php

function write_sheet_format_sidecar(string $csvFullPath, array $format): bool {
    $normalized = normalize_sheet_format_payload($format, 200, 26);
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    return file_put_contents(sheet_format_sidecar_path($csvFullPath), $json, LOCK_EX) !== false;
}

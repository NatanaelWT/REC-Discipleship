<?php

function public_material_session_number(array $row): int {
    $candidates = [
        trim((string) ($row['title'] ?? '')),
        trim((string) ($row['file_name'] ?? '')),
        basename(sanitize_relative_upload_path((string) ($row['path'] ?? ''))),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if (preg_match('/\bDG\s*[- ]?\s*[123]\s+Sesi\s+0?([1-9]|1[0-2])\b/i', $candidate, $matches) === 1) {
            return (int) $matches[1];
        }
        if (preg_match('/^\s*0?([1-9]|1[0-2])(?:[\s_.-]|$)/', $candidate, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    return 0;
}

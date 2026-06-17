<?php

function import_parse_msk_session_numbers(string $rawValue): array {
    $tokens = import_split_csv_tokens($rawValue);
    $sessions = [];
    foreach ($tokens as $token) {
        if (!is_numeric($token)) {
            continue;
        }
        $sessions[] = (int) $token;
    }
    return normalize_msk_session_numbers($sessions);
}

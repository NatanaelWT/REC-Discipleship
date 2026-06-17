<?php

function import_split_csv_tokens(string $value): array {
    $parts = preg_split('/[,;\n\r]+/', $value);
    if (!is_array($parts)) {
        return [];
    }
    $result = [];
    foreach ($parts as $part) {
        $clean = trim((string) $part);
        if ($clean === '') {
            continue;
        }
        $result[] = $clean;
    }
    return array_values(array_unique($result));
}

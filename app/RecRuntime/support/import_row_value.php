<?php

function import_row_value(array $row, array $headerMap, array $aliases): string {
    foreach ($aliases as $alias) {
        $aliasKey = strtolower(trim($alias));
        $aliasKey = preg_replace('/\s+/', '_', $aliasKey) ?? $aliasKey;
        if ($aliasKey === '' || !isset($headerMap[$aliasKey])) {
            continue;
        }
        $idx = (int) $headerMap[$aliasKey];
        $raw = $row[$idx] ?? '';
        return trim((string) $raw);
    }
    return '';
}

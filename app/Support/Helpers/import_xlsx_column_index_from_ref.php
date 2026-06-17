<?php

function import_xlsx_column_index_from_ref(string $cellRef): int {
    if (preg_match('/^[A-Z]+/i', $cellRef, $match) !== 1) {
        return 0;
    }
    $letters = strtoupper((string) ($match[0] ?? ''));
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $charCode = ord($letters[$i]);
        if ($charCode < 65 || $charCode > 90) {
            continue;
        }
        $index = ($index * 26) + ($charCode - 64);
    }
    return max(0, $index - 1);
}

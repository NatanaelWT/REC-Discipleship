<?php

function read_sheet_csv_file(string $fullPath, int $maxRows = 200, int $maxCols = 26): ?array {
    if (!is_file($fullPath)) {
        return null;
    }
    $fp = fopen($fullPath, 'r');
    if ($fp === false) {
        return null;
    }

    $rows = [];
    $count = 0;
    while (($row = fgetcsv($fp)) !== false) {
        if ($count >= $maxRows) {
            break;
        }
        if (!is_array($row)) {
            $row = [''];
        }
        $cells = [];
        foreach (array_slice($row, 0, $maxCols) as $cell) {
            $cells[] = normalize_sheet_cell_value($cell);
        }
        $rows[] = $cells;
        $count++;
    }
    fclose($fp);

    return normalize_sheet_rows_payload($rows, $maxRows, $maxCols);
}

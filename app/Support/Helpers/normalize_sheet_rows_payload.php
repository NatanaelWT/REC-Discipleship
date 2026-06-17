<?php

function normalize_sheet_rows_payload($rows, int $maxRows = 200, int $maxCols = 26): array {
    if (!is_array($rows)) {
        return [['']];
    }

    $normalized = [];
    $rowCount = 0;
    foreach ($rows as $row) {
        if ($rowCount >= $maxRows) {
            break;
        }
        $cells = [];
        if (is_array($row)) {
            $cellCount = 0;
            foreach ($row as $cell) {
                if ($cellCount >= $maxCols) {
                    break;
                }
                $cells[] = normalize_sheet_cell_value($cell);
                $cellCount++;
            }
        } else {
            $cells[] = normalize_sheet_cell_value($row);
        }
        $normalized[] = $cells;
        $rowCount++;
    }

    if (count($normalized) === 0) {
        return [['']];
    }

    while (count($normalized) > 1) {
        $lastRow = $normalized[count($normalized) - 1];
        $hasValue = false;
        foreach ($lastRow as $cell) {
            if (trim((string) $cell) !== '') {
                $hasValue = true;
                break;
            }
        }
        if ($hasValue) {
            break;
        }
        array_pop($normalized);
    }

    $usedCols = 1;
    foreach ($normalized as $row) {
        $lastValueIndex = -1;
        foreach ($row as $idx => $cell) {
            if (trim((string) $cell) !== '') {
                $lastValueIndex = (int) $idx;
            }
        }
        $usedCols = max($usedCols, $lastValueIndex + 1, count($row), 1);
    }
    $usedCols = min(max(1, $usedCols), $maxCols);

    foreach ($normalized as &$row) {
        $row = array_slice(array_values($row), 0, $usedCols);
        while (count($row) < $usedCols) {
            $row[] = '';
        }
    }
    unset($row);

    return $normalized;
}

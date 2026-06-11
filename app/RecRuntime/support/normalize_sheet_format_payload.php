<?php

function normalize_sheet_format_payload($format, int $maxRows = 200, int $maxCols = 26): array {
    if (!is_array($format)) {
        return ['rows' => [], 'cols' => [], 'freeze_rows' => 0];
    }
    $rowsRaw = $format['rows'] ?? ($format['row'] ?? []);
    $colsRaw = $format['cols'] ?? ($format['col'] ?? []);
    $freezeRaw = $format['freeze_rows'] ?? ($format['freezeRows'] ?? ($format['freeze'] ?? 0));
    $freezeRows = is_numeric($freezeRaw) ? (int) $freezeRaw : 0;
    if ($freezeRows < 0) {
        $freezeRows = 0;
    }
    if ($freezeRows > $maxRows) {
        $freezeRows = $maxRows;
    }
    return [
        'rows' => normalize_sheet_axis_format_payload($rowsRaw, $maxRows),
        'cols' => normalize_sheet_axis_format_payload($colsRaw, $maxCols),
        'freeze_rows' => $freezeRows,
    ];
}

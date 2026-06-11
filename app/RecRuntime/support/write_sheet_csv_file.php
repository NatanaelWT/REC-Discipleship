<?php

function write_sheet_csv_file(string $fullPath, array $rows): bool {
    $dir = dirname($fullPath);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }
    $fp = fopen($fullPath, 'w');
    if ($fp === false) {
        return false;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $row = [normalize_sheet_cell_value($row)];
        }
        if (fputcsv($fp, $row) === false) {
            fclose($fp);
            return false;
        }
    }
    fclose($fp);
    return true;
}

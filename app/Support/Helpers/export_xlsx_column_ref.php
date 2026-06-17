<?php

function export_xlsx_column_ref(int $index): string {
    $index = max(0, $index);
    $letters = '';
    $number = $index + 1;
    while ($number > 0) {
        $remainder = ($number - 1) % 26;
        $letters = chr(65 + $remainder) . $letters;
        $number = (int) floor(($number - 1) / 26);
    }
    return $letters;
}

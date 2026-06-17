<?php

function normalize_sheet_cell_value($value): string {
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
        $text = (string) $value;
    } else {
        $text = '';
    }
    return str_replace(["\r\n", "\r"], "\n", $text);
}

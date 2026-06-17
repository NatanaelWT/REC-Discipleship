<?php

function parse_bool_value($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }
    $text = strtolower(trim((string) $value));
    return in_array($text, ['1', 'true', 'yes', 'on'], true);
}

<?php

function normalize_church_file_category(string $value): string {
    $value = trim($value);
    $options = church_file_categories();
    if (!in_array($value, $options, true)) {
        return 'Lainnya';
    }
    return $value;
}

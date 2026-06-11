<?php

function normalize_member_gender_value(string $value): string {
    $value = trim($value);
    $allowed = ['Laki-laki', 'Perempuan'];
    if (!in_array($value, $allowed, true)) {
        return '';
    }
    return $value;
}

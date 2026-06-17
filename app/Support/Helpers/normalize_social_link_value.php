<?php

function normalize_social_link_value(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\\/\\//i', $value) !== 1) {
        $value = 'https://' . $value;
    }
    return $value;
}

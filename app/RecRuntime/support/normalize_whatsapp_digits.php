<?php

function normalize_whatsapp_digits(string $value): string {
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits !== '' && strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    }
    return $digits;
}

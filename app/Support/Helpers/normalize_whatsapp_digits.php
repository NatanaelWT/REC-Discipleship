<?php

function normalize_whatsapp_digits(string $value): string {
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits !== '' && strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    } elseif ($digits !== '' && strpos($digits, '8') === 0) {
        $digits = '62' . $digits;
    }
    return $digits;
}

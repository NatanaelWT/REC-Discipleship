<?php

function discipleship_unified_identity_key(string $fullName, string $whatsapp): string {
    $nameKey = strtolower(trim($fullName));
    if ($nameKey === '') {
        return '';
    }
    $waKey = preg_replace('/\D+/', '', $whatsapp) ?? '';
    if ($waKey !== '' && strpos($waKey, '0') === 0) {
        $waKey = '62' . substr($waKey, 1);
    }
    return $nameKey . '|' . $waKey;
}

<?php

function discipleship_unified_identity_key(string $fullName, string $whatsapp): string {
    $nameKey = strtolower(trim(preg_replace('/\s+/', ' ', $fullName) ?? $fullName));
    if ($nameKey === '') {
        return '';
    }
    $waKey = normalize_whatsapp_digits($whatsapp);

    return $nameKey . '|' . $waKey;
}
